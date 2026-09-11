<?php

declare(strict_types=1);

/**
 * Rutas del sitio público. Se irán agregando conforme avancen las
 * Fases 4-7 (menú, galería, contacto, etc.).
 *
 * @var \App\Core\Router $router definida en public/index.php
 */

use App\Controllers\Public\HomeController;

$router->get('/', [HomeController::class, 'index']);
