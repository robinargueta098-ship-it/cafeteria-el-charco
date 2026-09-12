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

        $this->vista('admin/lealtad/canjear', [
            'seccionActiva' => 'lealtad',
            'codigo'        => (string) ($_GET['codigo'] ?? ''),
            'recompensa'    => null,
            'error'         => null,
            'productos'     => (new Producto())->listarActivos(),
        ]);
    }

    /** Paso 1: solo consultar el código (sin canjear todavía) para mostrarle al cajero a quién pertenece. */
    public function consultarCodigo(): void
    {
        $this->requerirRol(1, 2);

        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $recompensa = $codigo !== '' ? (new Recompensa())->buscarPorCodigo($codigo) : null;

        $this->vista('admin/lealtad/canjear', [
            'seccionActiva' => 'lealtad',
            'codigo'        => $codigo,
            'recompensa'    => $recompensa,
            'error'         => $recompensa === null ? 'No se encontró ninguna recompensa con ese código.' : null,
            'productos'     => (new Producto())->listarActivos(),
        ]);
    }

    /** Paso 2: confirmar el canje (aquí sí se valida todo y se marca canjeada, con bloqueo contra doble canje). */
    public function confirmarCanje(): void
    {
        $this->requerirRol(1, 2);

        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::validarYCanjearRecompensa($codigo, $productoId, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Canje confirmado: {$resultado['producto_nombre']} entregado a {$resultado['cliente_nombre']}.");
            $this->redirigir('/admin/lealtad/canjear');
            return;
        }

        $this->vista('admin/lealtad/canjear', [
            'seccionActiva' => 'lealtad',
            'codigo'        => $codigo,
            'recompensa'    => (new Recompensa())->buscarPorCodigo($codigo),
            'error'         => $resultado['error'],
            'productos'     => (new Producto())->listarActivos(),
        ]);
    }
}
