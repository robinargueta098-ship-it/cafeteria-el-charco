<?php
/**
 * Layout del panel administrativo (con sesión iniciada). Se usa en
 * todas las pantallas del panel excepto login.
 *
 * @var string $seccionActiva clave de la sección activa, para resaltar
 *             el enlace correspondiente en el sidebar (ver $enlaces abajo)
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Permisos;
use App\Core\Vista;

$usuarioActual = Auth::usuario();
$esAdministrador = $usuarioActual !== null && $usuarioActual['rol_id'] === 1;
$esRepartidor = $usuarioActual !== null && $usuarioActual['rol_id'] === 3;
$seccionActiva = $seccionActiva ?? '';

// clave => [etiqueta, url, módulo]. Módulo 'admin' = solo Administrador; null = siempre; otro = según «Roles y permisos».
$enlaces = [
    'dashboard'      => ['Dashboard', '/admin', null],
    'pedidos'        => [$esRepartidor ? 'Entregas' : 'Pedidos', '/admin/pedidos', 'pedidos'],
    'reportes'       => ['Reportes', '/admin/reportes', 'reportes'],
    'productos'      => ['Productos', '/admin/productos', 'productos'],
    'categorias'     => ['Categorías', '/admin/categorias', 'categorias'],
    'galeria'        => ['Galería', '/admin/galeria', 'galeria'],
    'temporadas'     => ['Temporadas', '/admin/temporadas', 'temporadas'],
    'promociones'    => ['Promociones', '/admin/promociones', 'promociones'],
    'clientes'       => ['Clientes', '/admin/clientes', 'clientes'],
    'lealtad'        => ['Lealtad', '/admin/lealtad', 'lealtad'],
    'canjear'        => ['Escanear código', '/admin/lealtad/canjear', 'canjear'],
    'recompensas'    => ['Recompensas', '/admin/recompensas', 'recompensas'],
    'cupones'        => ['Cupones', '/admin/cupones', 'cupones'],
    'configuracion'  => ['Configuración', '/admin/configuracion', 'admin'],
    'usuarios'       => ['Usuarios', '/admin/usuarios', 'admin'],
    'roles'          => ['Roles y permisos', '/admin/roles', 'admin'],
    'auditoria'      => ['Auditoría', '/admin/auditoria', 'admin'],
];

$flash = Flash::tomar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel — Cafetería El Charco</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(Vista::asset('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php /* Marca "hay JavaScript" antes de pintar: en celular el menú lateral solo se
             oculta detrás del botón hamburguesa si el script que lo abre va a funcionar;
             sin JavaScript el menú queda visible y apilado arriba (ver main.css). */ ?>
    <script>document.documentElement.className += ' js';</script>
</head>
<body class="panel-body">
<div class="panel-layout">
    <aside class="panel-sidebar" id="panel-sidebar">
        <div class="panel-sidebar-marca">Cafetería El Charco</div>
        <nav aria-label="Navegación del panel">
            <ul class="panel-nav">
                <?php foreach ($enlaces as $clave => [$etiqueta, $url, $modulo]): ?>
                    <?php
                    if ($modulo === 'admin' && !$esAdministrador) { continue; }
                    if ($modulo !== null && $modulo !== 'admin' && !Permisos::puede($modulo, 'ver')) { continue; }
                    if ($clave === 'dashboard' && $esRepartidor) { continue; }   // el Repartidor entra directo a sus pantallas
                    ?>
                    <li>
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                           class="<?= $clave === $seccionActiva ? 'activo' : '' ?>">
                            <?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <form method="post" action="/admin/logout" class="panel-logout">
            <?= Csrf::campo() ?>
            <button type="submit" class="btn-enlace">Cerrar sesión</button>
        </form>
    </aside>

    <?php /* Fondo oscuro que se ve solo en celular con el menú abierto (oculto en escritorio). */ ?>
    <div class="panel-menu-fondo" data-panel-cerrar-menu></div>

    <div class="panel-contenido">
        <header class="panel-topbar">
            <?php /* Solo en celular (en escritorio estos dos elementos están ocultos). */ ?>
            <button type="button" class="panel-menu-boton" aria-controls="panel-sidebar" aria-expanded="false" aria-label="Abrir menú">
                <svg class="icono-hamburguesa" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                <svg class="icono-cerrar" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            </button>
            <span class="panel-topbar-marca">Cafetería El Charco</span>
            <span class="panel-topbar-usuario">Hola, <?= htmlspecialchars($usuarioActual['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </header>

        <main class="panel-main">
            <?php if ($flash !== null): ?>
                <div class="alerta alerta-<?= htmlspecialchars($flash['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['mensaje'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
