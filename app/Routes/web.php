<?php

declare(strict_types=1);

/**
 * Rutas del sitio público. Se irán agregando conforme avancen las
 * Fases 4-7 (menú, galería, contacto, etc.).
 *
 * @var \App\Core\Router $router definida en public/index.php
 */

use App\Controllers\Public\CuentaController;
use App\Controllers\Public\HomeController;

$router->get('/', [HomeController::class, 'index']);

// --- Cuenta simple del cliente (teléfono + PIN) ---
$router->get('/cuenta/registro', [CuentaController::class, 'mostrarRegistro']);
$router->post('/cuenta/registro', [CuentaController::class, 'registrar']);
$router->get('/cuenta/login', [CuentaController::class, 'mostrarLogin']);
$router->post('/cuenta/login', [CuentaController::class, 'iniciarSesion']);
$router->post('/cuenta/logout', [CuentaController::class, 'cerrarSesion']);
$router->get('/cuenta', [CuentaController::class, 'panel']);
