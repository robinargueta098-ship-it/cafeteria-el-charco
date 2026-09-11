<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Models\Configuracion;

class HomeController extends Controller
{
    public function index(): void
    {
        $config = (new Configuracion())->obtenerTodas();

        $this->vista('public/home', [
            'config' => $config,
        ]);
    }
}
