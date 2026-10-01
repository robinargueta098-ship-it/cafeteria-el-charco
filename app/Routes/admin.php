<?php

declare(strict_types=1);

/**
 * Rutas del panel administrativo. El control de acceso real (sesión +
 * rol) lo hace cada controlador con Controller::requerirRol(), no el
 * router — aquí solo se declara el mapa de URLs.
 *
 * @var \App\Core\Router $router definida en public/index.php
 */

use App\Controllers\Admin\AuditoriaController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoriaController;
use App\Controllers\Admin\ClienteController;
use App\Controllers\Admin\ConfiguracionController;
use App\Controllers\Admin\CuponPromocionController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\GaleriaController;
use App\Controllers\Admin\LealtadController;
use App\Controllers\Admin\PortadaController;
use App\Controllers\Admin\PromocionController;
use App\Controllers\Admin\ProductoController;
use App\Controllers\Admin\RecompensaController;
use App\Controllers\Admin\TemporadaController;
use App\Controllers\Admin\UsuarioController;

// --- Autenticación ---
$router->get('/admin/login', [AuthController::class, 'mostrarLogin']);
$router->post('/admin/login', [AuthController::class, 'iniciarSesion']);
$router->post('/admin/logout', [AuthController::class, 'cerrarSesion']);

// --- Dashboard ---
$router->get('/admin', [DashboardController::class, 'index']);

// --- Categorías ---
$router->get('/admin/categorias', [CategoriaController::class, 'index']);
$router->get('/admin/categorias/crear', [CategoriaController::class, 'mostrarCrear']);
$router->post('/admin/categorias/crear', [CategoriaController::class, 'crear']);
$router->get('/admin/categorias/{id}/editar', [CategoriaController::class, 'mostrarEditar']);
$router->post('/admin/categorias/{id}/editar', [CategoriaController::class, 'actualizar']);
$router->post('/admin/categorias/{id}/eliminar', [CategoriaController::class, 'eliminar']);

// --- Productos ---
$router->get('/admin/productos', [ProductoController::class, 'index']);
$router->get('/admin/productos/crear', [ProductoController::class, 'mostrarCrear']);
$router->post('/admin/productos/crear', [ProductoController::class, 'crear']);
$router->get('/admin/productos/{id}/editar', [ProductoController::class, 'mostrarEditar']);
$router->post('/admin/productos/{id}/editar', [ProductoController::class, 'actualizar']);
$router->post('/admin/productos/{id}/estado', [ProductoController::class, 'alternarEstado']);

// --- Galería ---
$router->get('/admin/galeria', [GaleriaController::class, 'index']);
$router->get('/admin/galeria/crear', [GaleriaController::class, 'mostrarCrear']);
$router->post('/admin/galeria/crear', [GaleriaController::class, 'crear']);
$router->get('/admin/galeria/{id}/editar', [GaleriaController::class, 'mostrarEditar']);
$router->post('/admin/galeria/{id}/editar', [GaleriaController::class, 'actualizar']);
$router->post('/admin/galeria/{id}/mover', [GaleriaController::class, 'mover']);
$router->post('/admin/galeria/{id}/eliminar', [GaleriaController::class, 'eliminar']);

// --- Temporadas ---
$router->get('/admin/temporadas', [TemporadaController::class, 'index']);
$router->get('/admin/temporadas/crear', [TemporadaController::class, 'mostrarCrear']);
$router->post('/admin/temporadas/crear', [TemporadaController::class, 'crear']);
$router->get('/admin/temporadas/{id}/editar', [TemporadaController::class, 'mostrarEditar']);
$router->post('/admin/temporadas/{id}/editar', [TemporadaController::class, 'actualizar']);
$router->post('/admin/temporadas/{id}/eliminar', [TemporadaController::class, 'eliminar']);

// --- Promociones ---
$router->get('/admin/promociones', [PromocionController::class, 'index']);
$router->get('/admin/promociones/crear', [PromocionController::class, 'mostrarCrear']);
$router->post('/admin/promociones/crear', [PromocionController::class, 'crear']);
$router->get('/admin/promociones/{id}/editar', [PromocionController::class, 'mostrarEditar']);
$router->post('/admin/promociones/{id}/editar', [PromocionController::class, 'actualizar']);
$router->post('/admin/promociones/{id}/eliminar', [PromocionController::class, 'eliminar']);

// --- Clientes ---
$router->get('/admin/clientes', [ClienteController::class, 'index']);
$router->get('/admin/clientes/crear', [ClienteController::class, 'mostrarCrear']);
$router->post('/admin/clientes/crear', [ClienteController::class, 'crear']);
$router->get('/admin/clientes/{id}/editar', [ClienteController::class, 'mostrarEditar']);
$router->post('/admin/clientes/{id}/editar', [ClienteController::class, 'actualizar']);

// --- Lealtad ---
// Las rutas literales van ANTES de la ruta con {id}: si no, "canjear"
// se interpretaría como un ID de cliente (el router usa la primera
// coincidencia, ver App\Core\Router).
$router->get('/admin/lealtad', [LealtadController::class, 'index']);
$router->get('/admin/lealtad/canjear', [LealtadController::class, 'mostrarCanjear']);
$router->post('/admin/lealtad/canjear/consultar', [LealtadController::class, 'consultarCodigo']);
$router->post('/admin/lealtad/canjear/sumar', [LealtadController::class, 'sumarConsumoPorQr']);
$router->post('/admin/lealtad/canjear/confirmar', [LealtadController::class, 'confirmarCanje']);
$router->post('/admin/lealtad/canjear/confirmar-cupon', [LealtadController::class, 'confirmarCanjeCupon']);
$router->get('/admin/lealtad/{id}', [LealtadController::class, 'mostrarCliente']);
$router->post('/admin/lealtad/{id}/consumo', [LealtadController::class, 'registrarConsumo']);
$router->post('/admin/lealtad/{id}/ajuste', [LealtadController::class, 'registrarAjuste']);

// --- Recompensas ---
$router->get('/admin/recompensas', [RecompensaController::class, 'index']);

// --- Cupones de promoción (§4.20) — cancelar es exclusivo de Administrador, ver el controlador ---
$router->get('/admin/cupones', [CuponPromocionController::class, 'index']);
$router->post('/admin/cupones/{id}/cancelar', [CuponPromocionController::class, 'cancelar']);

// --- Configuración ---
$router->get('/admin/configuracion', [ConfiguracionController::class, 'mostrar']);
$router->post('/admin/configuracion', [ConfiguracionController::class, 'actualizar']);

// Imágenes del fondo de la portada (se administran desde Configuración, tabla propia)
$router->post('/admin/configuracion/portada', [PortadaController::class, 'subir']);
$router->post('/admin/configuracion/portada/{id}/mover', [PortadaController::class, 'mover']);
$router->post('/admin/configuracion/portada/{id}/eliminar', [PortadaController::class, 'eliminar']);

// --- Usuarios ---
$router->get('/admin/usuarios', [UsuarioController::class, 'index']);
$router->get('/admin/usuarios/crear', [UsuarioController::class, 'mostrarCrear']);
$router->post('/admin/usuarios/crear', [UsuarioController::class, 'crear']);
$router->get('/admin/usuarios/{id}/editar', [UsuarioController::class, 'mostrarEditar']);
$router->post('/admin/usuarios/{id}/editar', [UsuarioController::class, 'actualizar']);

// --- Auditoría ---
$router->get('/admin/auditoria', [AuditoriaController::class, 'index']);
