<?php
/**
 * @var array<string,string> $config
 */
use App\Core\Vista;
use App\Core\WhatsApp;

$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
$descripcionCorta = $config['descripcion_corta'] ?? '';
$logo = $config['logo'] ?? '';
$whatsappNumero = $config['whatsapp_numero'] ?? '';
$enlaceWhatsappNav = WhatsApp::enlace($whatsappNumero, "Hola, escribo desde el sitio web de {$nombreSitio}.");

// Las secciones (#menu, #galeria…) viven solo en la página de inicio. Desde otra
// página (ej. /cuenta) un enlace "#menu" no llevaba a ninguna parte: hay que
// anteponer "/" para volver al inicio y desde ahí bajar a la sección.
$rutaPagina = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$anclaInicio = $rutaPagina === '' ? '' : '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($descripcionCorta !== ''): ?>
        <meta name="description" content="<?= htmlspecialchars($descripcionCorta, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(Vista::asset('/assets/css/main.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(Vista::asset('/assets/css/sitio.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="sitio-body">

<nav class="sitio-nav">
    <div class="sitio-nav-interior">
        <a href="<?= $anclaInicio ?>#inicio" class="sitio-nav-marca">
            <?php if ($logo !== ''): ?>
                <img src="<?= UPLOADS_URL ?>/sitio/<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <div class="sitio-nav-acciones">
            <?php if (!empty($mostrarCarrito)): ?>
                <?php /* Botón del carrito (solo en la página con menú). Lo activa carrito.js; sin JavaScript queda oculto. */ ?>
                <button type="button" class="nav-carrito" id="carrito-abrir" hidden aria-haspopup="dialog" aria-controls="carrito-panel" aria-label="Ver mi carrito">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H6"/></svg>
                    <span class="carrito-contador" id="carrito-contador" hidden>0</span>
                </button>
            <?php endif; ?>
            <button type="button" class="sitio-nav-boton-menu" aria-expanded="false" aria-label="Abrir menú de navegación">☰</button>
        </div>
        <div class="sitio-nav-enlaces">
            <a href="<?= $anclaInicio ?>#inicio">Inicio</a>
            <a href="<?= $anclaInicio ?>#menu">Menú</a>
            <a href="<?= $anclaInicio ?>#nosotros">Nosotros</a>
            <a href="<?= $anclaInicio ?>#galeria">Galería</a>
            <a href="<?= $anclaInicio ?>#ubicacion">Ubicación</a>
            <a href="<?= $anclaInicio ?>#lealtad">Lealtad</a>
            <a href="<?= $anclaInicio ?>#contacto">Contacto</a>
            <a href="/cuenta">Mi cuenta</a>
            <a href="<?= htmlspecialchars($enlaceWhatsappNav, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-whatsapp" target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">
                <?= Vista::iconoWhatsapp() ?><span class="texto-boton-whatsapp">WhatsApp</span>
            </a>
        </div>
    </div>
</nav>
