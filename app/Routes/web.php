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
use App\Controllers\Public\PedidoController;

$router->get('/', [HomeController::class, 'index']);

// --- Carrito: guardar el pedido (responde JSON; el navegador abre WhatsApp después) ---
$router->post('/pedido', [PedidoController::class, 'crear']);
$router->post('/pedido/confirmar', [PedidoController::class, 'confirmar']);

// --- Cuenta simple del cliente (teléfono + PIN) ---
$router->get('/cuenta/registro', [CuentaController::class, 'mostrarRegistro']);
$router->post('/cuenta/registro', [CuentaController::class, 'registrar']);
$router->get('/cuenta/login', [CuentaController::class, 'mostrarLogin']);
$router->post('/cuenta/login', [CuentaController::class, 'iniciarSesion']);
$router->get('/cuenta/cambiar-pin', [CuentaController::class, 'mostrarCambiarPin']);
$router->post('/cuenta/cambiar-pin', [CuentaController::class, 'cambiarPin']);
$router->post('/cuenta/logout', [CuentaController::class, 'cerrarSesion']);
$router->get('/cuenta', [CuentaController::class, 'panel']);
$router->post('/cuenta/promociones/{id}/reclamar', [CuentaController::class, 'reclamarCupon']);
