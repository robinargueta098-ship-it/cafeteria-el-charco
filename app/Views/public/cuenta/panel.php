<?php
/**
 * @var array<string,mixed> $cliente
 * @var int $umbral
 * @var array<int,array<string,mixed>> $recompensas
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Csrf;
use App\Core\CodigoQr;

require APP_PATH . '/Views/layouts/header_publico.php';

$saldo = (int) $cliente['cafes_acumulados'];
$porcentaje = min(100, (int) round(($saldo / max(1, $umbral)) * 100));
$disponibles = array_filter($recompensas, fn ($r) => $r['estado'] === 'disponible');
$historicas = array_filter($recompensas, fn ($r) => $r['estado'] !== 'disponible');

$etiquetasEstado = [
    'disponible' => 'Disponible', 'canjeada' => 'Canjeada',
    'cancelada' => 'Cancelada', 'vencida' => 'Vencida',
];
?>

<section class="sitio-seccion" style="min-height:60vh;">
    <div class="sitio-contenedor" style="max-width:640px;">
        <div class="panel-encabezado">
            <h1>Hola, <?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
            <form method="post" action="/cuenta/logout">
                <?= Csrf::campo() ?>
                <button type="submit" class="btn-enlace">Cerrar sesión</button>
            </form>
        </div>

        <div class="lealtad-tarjeta" style="margin-bottom:24px;">
            <div class="lealtad-numero"><?= $saldo ?> / <?= $umbral ?></div>
            <div class="progreso-lealtad">
                <div class="progreso-lealtad-barra" style="width:<?= $porcentaje ?>%;"></div>
            </div>
            <p>Cafés acumulados hacia tu próximo café gratis.</p>
        </div>

        <?php if ($disponibles !== []): ?>
            <h2>🎉 Tienes <?= count($disponibles) ?> recompensa(s) disponible(s)</h2>
            <p class="sitio-seccion-intro">Muestra este código QR en el mostrador para canjearlo, o díctale el código al cajero.</p>
            <?php foreach ($disponibles as $r): ?>
                <?php $qr = CodigoQr::dataUri($r['codigo']); ?>
                <div class="tarjeta" style="text-align:center;margin-bottom:16px;">
                    <?php if ($qr !== null): ?>
                        <img class="qr-recompensa" src="<?= htmlspecialchars($qr, ENT_QUOTES, 'UTF-8') ?>" width="240" height="240"
                             alt="Código QR de tu recompensa <?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="codigo-recompensa-texto" style="font-size:1.8rem;font-weight:700;letter-spacing:2px;color:var(--color-verde);">
                        <?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if ($r['fecha_vencimiento'] !== null): ?>
                        <p class="campo-ayuda">Válido hasta <?= htmlspecialchars(substr($r['fecha_vencimiento'], 0, 10), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2>Historial de recompensas</h2>
        <div class="tarjeta tabla-contenedor">
            <?php if ($historicas === []): ?>
                <p class="tabla-vacio">Todavía no tienes recompensas anteriores.</p>
            <?php else: ?>
                <table class="tabla">
                    <thead><tr><th>Código</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php foreach ($historicas as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $etiquetasEstado[$r['estado']] ?? $r['estado'] ?></td>
                                <td><?= htmlspecialchars(substr((string) ($r['fecha_canjeada'] ?? $r['fecha_generada']), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
