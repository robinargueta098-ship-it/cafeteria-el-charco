<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Models\Configuracion;
use App\Models\Galeria;
use App\Models\HorarioAtencion;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Temporada;
use App\Services\LealtadService;

class HomeController extends Controller
{
    public function index(): void
    {
        $productoModelo = new Producto();

        $this->vista('public/home', [
            'config'          => (new Configuracion())->obtenerTodas(),
            'horarios'        => (new HorarioAtencion())->listarTodos(),
            'menu'            => $productoModelo->listarMenuPublico(),
            'masVendidos'     => $productoModelo->listarMasVendidos(),
            'destacados'      => $productoModelo->listarDestacados(),
            'temporadas'      => (new Temporada())->listarVigentes(),
            'productosTemporada' => $productoModelo->listarEnTemporadaVigente(),
            'galeria'         => (new Galeria())->listarActivas(),
            'promociones'     => (new Promocion())->listarVigentesAhora(),
            'umbralLealtad'   => LealtadService::obtenerUmbral(),
        ]);
    }
}
