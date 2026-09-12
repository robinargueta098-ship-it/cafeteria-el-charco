<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\BitacoraAuditoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Promocion;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $usuario = Auth::usuario();
        $esAdministrador = $usuario['rol_id'] === 1;

        $datos = [
            'seccionActiva' => 'dashboard',
            'usuario'       => $usuario,
            'esAdmin'       => $esAdministrador,
            'clientesTotal' => (new Cliente())->contarTotal(),
            'recompensasDisponibles' => (new Cliente())->contarConRecompensaDisponible(),
        ];

        if ($esAdministrador) {
            $datos['productosActivos'] = (new Producto())->contarActivos();
            $datos['promocionesVigentes'] = (new Promocion())->contarVigentesHoy();
            $datos['ultimaActividad'] = (new BitacoraAuditoria())->listar([], 1)['registros'];
            $datos['ultimaActividad'] = array_slice($datos['ultimaActividad'], 0, 5);
        }

        $this->vista('admin/dashboard', $datos);
    }
}
