<?php

declare(strict_types=1);

/**
 * Rutas del panel administrativo. Se irán agregando conforme avancen
 * las Fases 4-6 (productos, categorías, promociones, clientes,
 * lealtad, configuración, usuarios, auditoría).
 *
 * @var \App\Core\Router $router definida en public/index.php
 */

use App\Controllers\Admin\AuthController;

$router->get('/admin/login', [AuthController::class, 'mostrarLogin']);
$router->post('/admin/login', [AuthController::class, 'iniciarSesion']);
$router->post('/admin/logout', [AuthController::class, 'cerrarSesion']);
$router->get('/admin', [AuthController::class, 'panel']);
