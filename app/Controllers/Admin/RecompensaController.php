<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\LealtadMovimiento;

class RecompensaController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $this->vista('admin/lealtad/recompensas', [
            'seccionActiva' => 'recompensas',
            'canjes'        => (new LealtadMovimiento())->historialDeCanjes(),
        ]);
    }
}
