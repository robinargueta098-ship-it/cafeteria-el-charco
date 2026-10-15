<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Entrada;
use App\Core\Flash;
use App\Core\Permisos;
use App\Core\Validator;
use App\Models\Pedido;
use App\Services\PedidoEstadoService;

/**
 * Módulo "Pedidos" del panel (Administrador y Cajero): listado, detalle y cambio de estado.
 * Los pedidos no se borran ni se editan; solo avanzan por la máquina de estados (PedidoEstadoService).
 */
class PedidoController extends Controller
{
    public function index(): void
    {
        $this->requerirPermiso('pedidos', 'ver');

        $esRepartidor = (int) Auth::usuario()['rol_id'] === 3;

        $filtros = [
            'estado' => Entrada::texto($_GET, 'estado', ''),
            'desde'  => Entrada::texto($_GET, 'desde', ''),
            'hasta'  => Entrada::texto($_GET, 'hasta', ''),
            'q'      => mb_substr(trim(Entrada::texto($_GET, 'q', '')), 0, 40),
        ];
        $avisos = [];

        if (!in_array($filtros['estado'], Pedido::ESTADOS, true)) {
            $filtros['estado'] = '';
        }
        if ($esRepartidor) {
            // El Repartidor solo ve lo que le toca entregar: sin filtros ni búsqueda.
            $filtros = ['estado' => 'confirmado', 'desde' => '', 'hasta' => '', 'q' => ''];
        }
        foreach (['desde' => 'La fecha «desde»', 'hasta' => 'La fecha «hasta»'] as $campo => $etiqueta) {
            if ($filtros[$campo] !== '' && !Validator::esFechaValida($filtros[$campo])) {
                $avisos[] = $etiqueta . ' no es una fecha válida y se ignoró.';
                $filtros[$campo] = '';
            }
        }
        if ($filtros['desde'] !== '' && $filtros['hasta'] !== '' && $filtros['desde'] > $filtros['hasta']) {
            $avisos[] = 'La fecha «desde» es posterior a «hasta»: se intercambiaron.';
            list($filtros['desde'], $filtros['hasta']) = [$filtros['hasta'], $filtros['desde']];
        }

        $modelo = new Pedido();
        $pagina = max(1, (int) Entrada::texto($_GET, 'pagina', '1'));
        $resultado = $modelo->listar($filtros, $pagina);

        $this->vista('admin/pedidos/index', [
            'seccionActiva' => 'pedidos',
            'filtros'       => $filtros,
            'avisos'        => $avisos,
            'filas'         => $resultado['filas'],
            'total'         => $resultado['total'],
            'pagina'        => $resultado['pagina'],
            'totalPaginas'  => $resultado['totalPaginas'],
            'pendientes'    => $modelo->contarPendientesDeConfirmar(),
            'sinConfirmar'  => $modelo->contarSinConfirmarPorElCliente(),
            'esRepartidor'  => $esRepartidor,
        ]);
    }

    public function mostrar(string $id): void
    {
        $this->requerirPermiso('pedidos', 'ver');

        $usuario = Auth::usuario();
        $esRepartidor = (int) $usuario['rol_id'] === 3;

        $modelo = new Pedido();
        $pedido = $modelo->buscarPorId((int) $id);
        // El Repartidor solo puede abrir pedidos confirmados (los que tiene que entregar).
        if ($pedido === null || ($esRepartidor && $pedido['estado'] !== 'confirmado')) {
            $this->error404();
        }

        $this->vista('admin/pedidos/detalle', [
            'seccionActiva' => 'pedidos',
            'pedido'        => $pedido,
            'items'         => $modelo->items((int) $id),
            'historial'     => $modelo->historial((int) $id),
            'acciones'      => Permisos::puede('pedidos', 'editar')
                ? PedidoEstadoService::disponibles((string) $pedido['estado'], (int) $usuario['rol_id'])
                : [],
            'esRepartidor'  => $esRepartidor,
        ]);
    }

    public function cambiarEstado(string $id): void
    {
        $this->requerirPermiso('pedidos', 'editar');

        $usuario = Auth::usuario();
        $resultado = PedidoEstadoService::cambiar(
            (int) $id,
            Entrada::texto($_POST, 'estado_actual', ''),
            Entrada::texto($_POST, 'nuevo_estado', ''),
            (int) $usuario['id'],
            (int) $usuario['rol_id'],
            Entrada::texto($_POST, 'motivo', '')
        );

        if ($resultado['ok']) {
            Flash::exito('Pedido #' . $resultado['codigo'] . ' actualizado correctamente.');
        } else {
            Flash::error($resultado['error']);
        }

        // Al terminar una entrega, el Repartidor vuelve a su lista (el pedido ya no le aparece).
        $this->redirigir($resultado['ok'] && (int) $usuario['rol_id'] === 3 ? '/admin/pedidos' : '/admin/pedidos/' . (int) $id);
    }
}
