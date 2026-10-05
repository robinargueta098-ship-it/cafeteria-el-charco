<?php
/**
 * @var array<string,mixed> $cliente
 * @var int $umbral
 * @var array<int,array<string,mixed>> $recompensas
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 * @var array{tipo:string,mensaje:string}|null $flash
 * @var array<int,array<string,mixed>> $promocionesLealtad
 * @var array<int,array<string,mixed>|null> $cuponesPorPromocion clave = id de la promoción
 */
use App\Core\Csrf;
use App\Core\CodigoQr;
use App\Core\Vista;

require APP_PATH . '/Views/layouts/header_publico.php';

$saldo = (int) $cliente['cafes_acumulados'];
$disponibles = array_filter($recompensas, fn ($r) => $r['estado'] === 'disponible');
$historicas = array_filter($recompensas, fn ($r) => $r['estado'] !== 'disponible');

// Justo al completar el umbral, el saldo ya volvió a 0 (empieza la siguiente tarjeta) aunque
// la recompensa todavía no se canjeó — sin esto el contador mostraba "0 / 10" en el mismo
// instante en que el cliente completó sus 10 cafés, como si no hubiera pasado nada. Se
// muestra la tarjeta llena (100%) hasta que el cliente empiece a acumular la siguiente de verdad.
// Misma regla que el panel administrativo (Cliente::progresoTarjeta): con varias recompensas
// sin canjear y saldo 0 se ve la tarjeta llena de todas (20 / 20, 30 / 30…), no solo 10 / 10.
$cliente['recompensas_disponibles'] = count($disponibles);
$progreso = \App\Models\Cliente::progresoTarjeta($cliente, (int) $umbral);
$tarjetaRecienCompletada = $progreso['completa'];
$numeroMostrado = $progreso['mostrado'];
$totalMostrado = $progreso['total'];
$porcentaje = min(100, (int) round(($numeroMostrado / max(1, $totalMostrado)) * 100));
$cantidadGratis = count($disponibles);
$textoGratis = $cantidadGratis === 1 ? 'tu café gratis está' : "tus {$cantidadGratis} cafés gratis están";

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

        <?php if ($flash !== null): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flash['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($flash['mensaje'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($disponibles !== []): ?>
            <?php /* Arriba del contador, y no debajo: es la explicación de por qué el
                     contador está en 0 — no se ve como que el cliente perdió su progreso,
                     se ve como que ya lo cambió por un café gratis. */ ?>
            <div class="tarjeta lealtad-recompensa-lista" style="text-align:center;margin-bottom:16px;border-color:var(--color-verde);border-width:2px;">
                <?= Vista::ICONO_TAZA_TITULO ?>
                <h2 style="margin-top:0;">¡Tienes <?= count($disponibles) === 1 ? 'un café gratis listo' : (count($disponibles) . ' cafés gratis listos') ?> para reclamar!</h2>
                <p class="sitio-seccion-intro">Muestra este código QR en el mostrador, o díctale el código al cajero.</p>
                <?php foreach ($disponibles as $r): ?>
                    <?php $qr = CodigoQr::dataUri($r['codigo']); ?>
                    <div style="margin-bottom:16px;">
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
            </div>
        <?php endif; ?>

        <div class="lealtad-tarjeta" style="margin-bottom:24px;">
            <div class="lealtad-numero"><?= $numeroMostrado ?> / <?= $totalMostrado ?></div>
            <div class="progreso-lealtad">
                <div class="progreso-lealtad-barra" style="width:<?= $porcentaje ?>%;"></div>
            </div>
            <?php if ($tarjetaRecienCompletada): ?>
                <p>¡Completaste tus <?= $totalMostrado ?> cafés! <?= ucfirst($textoGratis) ?> arriba, listo<?= $cantidadGratis === 1 ? '' : 's' ?> para reclamar.</p>
            <?php elseif ($disponibles !== []): ?>
                <p>Ya tienes <?= $cantidadGratis === 1 ? 'un café gratis' : "{$cantidadGratis} cafés gratis" ?> por reclamar (arriba). Esto es lo que llevas para el siguiente.</p>
            <?php else: ?>
                <p>Cafés acumulados hacia tu próximo café gratis.</p>
            <?php endif; ?>
        </div>

        <?php /*
            Promociones vigentes "combinables con lealtad" (docs/arquitectura.md §4.20):
            un cupón real, de un solo uso, por cliente y por promoción. Si el cliente
            todavía no lo reclamó, se ofrece el botón; si ya lo tiene, se muestra su
            código/QR igual que una recompensa de café gratis (nunca las dos cosas).
        */ ?>
        <?php foreach ($promocionesLealtad as $promo): ?>
            <?php $cupon = $cuponesPorPromocion[(int) $promo['id']] ?? null; ?>
            <div class="tarjeta" style="margin-bottom:24px;border-color:var(--color-verde);<?= $cupon !== null && $cupon['estado'] === 'disponible' ? 'text-align:center;' : '' ?>">
                <p style="margin:0;">
                    <strong>Promoción especial:</strong>
                    <?= htmlspecialchars($promo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if (($etiqueta = Vista::etiquetaPromocion($promo)) !== ''): ?>
                        — <span style="color:var(--color-verde);font-weight:700;"><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($promo['descripcion'])): ?>
                        <br><?= htmlspecialchars($promo['descripcion'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <br><span class="campo-ayuda">Válida hasta <?= htmlspecialchars(Vista::fechaLarga($promo['fecha_fin']), ENT_QUOTES, 'UTF-8') ?>.</span>
                </p>

                <?php if ($cupon === null): ?>
                    <form method="post" action="/cuenta/promociones/<?= (int) $promo['id'] ?>/reclamar" style="margin-top:12px;">
                        <?= Csrf::campo() ?>
                        <button type="submit" class="btn btn-verde">Reclamar cupón</button>
                    </form>
                <?php elseif ($cupon['estado'] === 'disponible'): ?>
                    <?php $qrCupon = CodigoQr::dataUri($cupon['codigo']); ?>
                    <div style="margin-top:12px;">
                        <?php if ($qrCupon !== null): ?>
                            <img class="qr-recompensa" src="<?= htmlspecialchars($qrCupon, ENT_QUOTES, 'UTF-8') ?>" width="200" height="200"
                                 alt="Código QR de tu cupón <?= htmlspecialchars($cupon['codigo'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <div class="codigo-recompensa-texto" style="font-size:1.6rem;font-weight:700;letter-spacing:2px;color:var(--color-verde);">
                            <?= htmlspecialchars($cupon['codigo'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <p class="campo-ayuda" style="margin:4px 0 0;">Muestra este código en el mostrador, o díctaselo al cajero.</p>
                    </div>
                <?php elseif ($cupon['estado'] === 'canjeado'): ?>
                    <p class="campo-ayuda" style="margin-top:8px;">
                        Ya canjeaste este cupón el <?= htmlspecialchars(substr((string) $cupon['fecha_canje'], 0, 10), ENT_QUOTES, 'UTF-8') ?>.
                    </p>
                <?php else: ?>
                    <p class="campo-ayuda" style="margin-top:8px;">Este cupón ya no está disponible.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($codigoQr)): ?>
            <?php $qrPersonal = CodigoQr::dataUri($codigoQr); ?>
            <div class="tarjeta" style="text-align:center;margin-bottom:24px;">
                <h2 style="margin-top:0;">Tu QR para sumar cafés</h2>
                <p class="campo-ayuda" style="margin-bottom:12px;">Muéstralo en el mostrador cada vez que compres: el cajero lo escanea y te suma el café. Es siempre el mismo.</p>
                <?php if ($qrPersonal !== null): ?>
                    <img class="qr-recompensa" src="<?= htmlspecialchars($qrPersonal, ENT_QUOTES, 'UTF-8') ?>" width="240" height="240"
                         alt="Tu código QR personal <?= htmlspecialchars($codigoQr, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div class="codigo-recompensa-texto" style="font-size:1.4rem;font-weight:700;letter-spacing:2px;color:var(--color-cafe-oscuro);">
                    <?= htmlspecialchars($codigoQr, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($disponibles === []): ?>
            <div class="tarjeta" style="text-align:center;margin-bottom:24px;">
                <p style="margin:0;">
                    Todavía no tienes una recompensa disponible. Cuando completes
                    <?= (int) $umbral ?> cafés (te faltan <?= max(0, $umbral - $saldo) ?>), aquí aparecerá tu
                    QR de recompensa para canjear un café gratis.
                </p>
            </div>
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
