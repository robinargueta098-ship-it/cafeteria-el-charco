<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Cliente;
use App\Models\LealtadMovimiento;
use App\Models\Producto;
use App\Models\Recompensa;
use App\Services\LealtadService;

class LealtadController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $termino = trim((string) ($_GET['q'] ?? ''));

        $this->vista('admin/lealtad/buscar', [
            'seccionActiva' => 'lealtad',
            'clientes'      => $termino !== '' ? (new Cliente())->buscar($termino) : [],
            'termino'       => $termino,
        ]);
    }

    public function mostrarCliente(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $this->vista('admin/lealtad/cliente', [
            'seccionActiva' => 'lealtad',
            'cliente'       => $cliente,
            'umbral'        => LealtadService::obtenerUmbral(),
            'historial'     => (new LealtadMovimiento())->historialPorCliente((int) $id),
            'recompensas'   => (new Recompensa())->listarPorCliente((int) $id),
        ]);
    }

    public function registrarConsumo(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarConsumo((int) $id, $cantidad, $usuario['id']);

        if ($resultado['ok']) {
            $mensaje = "Se registraron {$cantidad} café(s) para {$cliente['nombre']}.";
            if ($resultado['codigos_generados'] !== []) {
                $n = count($resultado['codigos_generados']);
                $codigos = implode(', ', $resultado['codigos_generados']);
                $mensaje .= " ¡Se generó" . ($n > 1 ? "ron {$n} recompensas" : " 1 recompensa") . "! Código(s): {$codigos}.";
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    public function registrarAjuste(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $tipo = (string) ($_POST['tipo'] ?? '');
        $cantidad = (int) ($_POST['cantidad'] ?? 0);
        $motivo = trim((string) ($_POST['motivo'] ?? ''));
        $usuario = Auth::usuario();

        $resultado = LealtadService::registrarAjuste((int) $id, $cantidad, $tipo, $motivo, $usuario['id']);

        if ($resultado['ok']) {
            $mensaje = 'Ajuste registrado correctamente.';
            if ($resultado['codigos_generados'] !== []) {
                $mensaje .= ' Se generó recompensa: ' . implode(', ', $resultado['codigos_generados']) . '.';
            }
            Flash::exito($mensaje);
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    public function mostrarCanjear(): void
    {
        $this->requerirRol(1, 2);

        $this->vistaCanjear(
            Cliente::normalizarTelefono((string) ($_GET['telefono'] ?? '')),
            Recompensa::normalizarCodigo((string) ($_GET['codigo'] ?? '')),
            null,
            null
        );
    }

    /**
     * Paso 1: consultar (sin canjear todavía). El cajero identifica al
     * cliente por su teléfono Y escribe el código; el detalle de la
     * recompensa solo se muestra si ese código es realmente de ese cliente.
     */
    public function consultarCodigo(): void
    {
        $this->requerirRol(1, 2);

        $telefono = Cliente::normalizarTelefono((string) ($_POST['telefono'] ?? ''));
        $codigo = Recompensa::normalizarCodigo((string) ($_POST['codigo'] ?? ''));

        [$cliente, $recompensa, $error] = $this->resolverCanje($telefono, $codigo);

        $this->vistaCanjear($telefono, $codigo, $recompensa, $error);
    }

    /**
     * Paso 2: confirmar el canje. El cliente se vuelve a resolver desde el
     * teléfono en el servidor y LealtadService verifica de nuevo, con la
     * fila bloqueada, que el código sea suyo, esté disponible y no haya
     * vencido — nada de lo mostrado en el paso 1 se da por bueno.
     */
    public function confirmarCanje(): void
    {
        $this->requerirRol(1, 2);

        $telefono = Cliente::normalizarTelefono((string) ($_POST['telefono'] ?? ''));
        $codigo = Recompensa::normalizarCodigo((string) ($_POST['codigo'] ?? ''));
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $usuario = Auth::usuario();

        $cliente = $telefono !== '' ? (new Cliente())->buscarPorTelefono($telefono) : null;

        if ($cliente === null) {
            $this->vistaCanjear($telefono, $codigo, null, 'No se encontró un cliente con ese teléfono.');
            return;
        }

        $resultado = LealtadService::validarYCanjearRecompensa($codigo, (int) $cliente['id'], $productoId, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Canje confirmado: {$resultado['producto_nombre']} entregado a {$resultado['cliente_nombre']}.");
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        [, $recompensa] = $this->resolverCanje($telefono, $codigo);

        $this->vistaCanjear($telefono, $codigo, $recompensa, $resultado['error']);
    }

    /**
     * Busca al cliente por teléfono y la recompensa por código, y solo
     * devuelve la recompensa si pertenece a ese cliente.
     *
     * @return array{0:array<string,mixed>|null,1:array<string,mixed>|null,2:string|null} [cliente, recompensa, error]
     */
    private function resolverCanje(string $telefono, string $codigo): array
    {
        if ($telefono === '' || $codigo === '') {
            return [null, null, 'Escribe el teléfono del cliente y el código de la recompensa.'];
        }

        $cliente = (new Cliente())->buscarPorTelefono($telefono);
        if ($cliente === null) {
            return [null, null, 'No se encontró un cliente con ese teléfono.'];
        }

        $recompensa = (new Recompensa())->buscarPorCodigo($codigo);
        if ($recompensa === null) {
            return [$cliente, null, 'No se encontró ninguna recompensa con ese código.'];
        }

        if ((int) $recompensa['cliente_id'] !== (int) $cliente['id']) {
            return [$cliente, null, 'Ese código no pertenece a este cliente.'];
        }

        return [$cliente, $recompensa, null];
    }

    private function vistaCanjear(string $telefono, string $codigo, ?array $recompensa, ?string $error): void
    {
        $this->vista('admin/lealtad/canjear', [
            'seccionActiva' => 'lealtad',
            'telefono'      => $telefono,
            'codigo'        => $codigo,
            'recompensa'    => $recompensa,
            'error'         => $error,
            'productos'     => (new Producto())->listarActivos(),
        ]);
    }
}
