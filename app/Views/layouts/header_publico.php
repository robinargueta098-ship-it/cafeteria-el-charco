<?php
/**
 * @var array<string,string> $config
 */
use App\Core\WhatsApp;

$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
$descripcionCorta = $config['descripcion_corta'] ?? '';
$logo = $config['logo'] ?? '';
$whatsappNumero = $config['whatsapp_numero'] ?? '';
$enlaceWhatsappNav = WhatsApp::enlace($whatsappNumero, "Hola, escribo desde el sitio web de {$nombreSitio}.");
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
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/sitio.css">
</head>
<body class="sitio-body">

<nav class="sitio-nav">
    <div class="sitio-nav-interior">
        <a href="#inicio" class="sitio-nav-marca">
            <?php if ($logo !== ''): ?>
                <img src="<?= UPLOADS_URL ?>/sitio/<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <button type="button" class="sitio-nav-boton-menu" aria-expanded="false" aria-label="Abrir menú de navegación">☰</button>
        <div class="sitio-nav-enlaces">
            <a href="#inicio">Inicio</a>
            <a href="#menu">Menú</a>
            <a href="#nosotros">Nosotros</a>
            <a href="#galeria">Galería</a>
            <a href="#ubicacion">Ubicación</a>
            <a href="#lealtad">Lealtad</a>
            <a href="#contacto">Contacto</a>
            <a href="<?= htmlspecialchars($enlaceWhatsappNav, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-verde" target="_blank" rel="noopener">WhatsApp</a>
        </div>
    </div>
</nav>
