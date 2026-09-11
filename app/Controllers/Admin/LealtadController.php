<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Models\Cliente;
use App\Models\LealtadMovimiento;
use App\Models\Producto;
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
            'productos'     => (new Producto())->listarActivos(),
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
            Flash::exito("Se registraron {$cantidad} café(s) para {$cliente['nombre']}.");
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }

    public function canjear(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $productoId = (int) ($_POST['producto_id'] ?? 0);
        $usuario = Auth::usuario();

        $resultado = LealtadService::canjear((int) $id, $productoId, $usuario['id']);

        if ($resultado['ok']) {
            Flash::exito("Recompensa canjeada para {$cliente['nombre']}.");
        } else {
            Flash::error($resultado['error']);
        }

        $this->redirigir('/admin/lealtad/' . $id);
    }
}
