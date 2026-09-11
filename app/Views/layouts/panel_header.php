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

$usuarioActual = Auth::usuario();
$esAdministrador = $usuarioActual !== null && $usuarioActual['rol_id'] === 1;
$seccionActiva = $seccionActiva ?? '';

// clave => [etiqueta, url, solo_admin]
$enlaces = [
    'dashboard'      => ['Dashboard', '/admin', false],
    'productos'      => ['Productos', '/admin/productos', true],
    'categorias'     => ['Categorías', '/admin/categorias', true],
    'galeria'        => ['Galería', '/admin/galeria', true],
    'temporadas'     => ['Temporadas', '/admin/temporadas', true],
    'promociones'    => ['Promociones', '/admin/promociones', true],
    'clientes'       => ['Clientes', '/admin/clientes', false],
    'lealtad'        => ['Lealtad', '/admin/lealtad', false],
    'recompensas'    => ['Recompensas', '/admin/recompensas', false],
    'configuracion'  => ['Configuración', '/admin/configuracion', true],
    'usuarios'       => ['Usuarios', '/admin/usuarios', true],
    'auditoria'      => ['Auditoría', '/admin/auditoria', true],
];

$flash = Flash::tomar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel — Cafetería El Charco</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="panel-body">
<div class="panel-layout">
    <aside class="panel-sidebar">
        <div class="panel-sidebar-marca">Cafetería El Charco</div>
        <nav>
            <ul class="panel-nav">
                <?php foreach ($enlaces as $clave => [$etiqueta, $url, $soloAdmin]): ?>
                    <?php if ($soloAdmin && !$esAdministrador) continue; ?>
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

    <div class="panel-contenido">
        <header class="panel-topbar">
            <span>Hola, <?= htmlspecialchars($usuarioActual['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </header>

        <main class="panel-main">
            <?php if ($flash !== null): ?>
                <div class="alerta alerta-<?= htmlspecialchars($flash['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['mensaje'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
