<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Models\Recompensa;

class RecompensaController extends Controller
{
    private const ESTADOS_VALIDOS = ['disponible', 'canjeada', 'cancelada', 'vencida'];

    public function index(): void
    {
        $this->requerirRol(1, 2);

        $estado = Entrada::texto($_GET, 'estado', '');
        $filtros = in_array($estado, self::ESTADOS_VALIDOS, true) ? ['estado' => $estado] : [];

        $this->vista('admin/lealtad/recompensas', [
            'seccionActiva' => 'recompensas',
            'canjes'        => (new Recompensa())->listarTodas($filtros),
            'estadoFiltro'  => $estado,
        ]);
    }
}
