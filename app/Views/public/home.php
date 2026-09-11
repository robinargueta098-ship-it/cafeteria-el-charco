<?php
/**
 * Portada pública — versión mínima de la Fase 3 (Core).
 * El contenido completo (hero, destacados, menú, galería, ubicación,
 * lealtad, promociones...) se construye en las Fases 4-7, ver
 * docs/arquitectura.md §3.1.
 *
 * @var array<string,string> $config
 */
require APP_PATH . '/Views/layouts/header_publico.php';

$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
$instagram   = $config['instagram_usuario'] ?? '';
$facebook    = $config['facebook_nombre'] ?? '';
?>

<section class="tarjeta">
    <h1><?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Sitio en construcción — Fase 3 del proyecto (núcleo del sistema).</p>

    <?php if ($instagram !== ''): ?>
        <p>Instagram/TikTok: <?= htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($facebook !== ''): ?>
        <p>Facebook: <?= htmlspecialchars($facebook, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
