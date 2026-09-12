<?php
/**
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Vista;
use App\Core\WhatsApp;

$whatsappNumero = $config['whatsapp_numero'] ?? '';
$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
$instagram = $config['instagram_usuario'] ?? '';
$facebookNombre = $config['facebook_nombre'] ?? '';
$facebookUrl = $config['facebook_url'] ?? '';
$direccion = $config['direccion_texto'] ?? '';

$nombresDias = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
$hayHorario = false;
foreach ($horarios as $h) {
    if ((int) $h['cerrado'] === 0) {
        $hayHorario = true;
        break;
    }
}
?>

<footer class="sitio-footer" id="contacto">
    <div class="sitio-contenedor">
        <h2 style="color:var(--color-beige-claro);">Contacto</h2>

        <div class="redes-lista" style="margin-bottom:24px;">
            <?php if ($whatsappNumero !== ''): ?>
                <a href="<?= htmlspecialchars(WhatsApp::enlace($whatsappNumero, "Hola, escribo desde el sitio web de {$nombreSitio}."), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">📱 WhatsApp</a>
            <?php endif; ?>
            <?php if ($instagram !== ''): ?>
                <a href="https://www.instagram.com/<?= htmlspecialchars(ltrim($instagram, '@'), ENT_QUOTES, 'UTF-8') ?>/" target="_blank" rel="noopener">📷 <?= htmlspecialchars($instagram, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
            <?php if ($facebookNombre !== ''): ?>
                <?php if ($facebookUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">👍 <?= htmlspecialchars($facebookNombre, ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                    <span>👍 <?= htmlspecialchars($facebookNombre, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <p><?= $direccion !== '' ? htmlspecialchars($direccion, ENT_QUOTES, 'UTF-8') : Vista::pendiente('dirección exacta') ?></p>

        <?php if ($hayHorario): ?>
            <table class="horario-tabla">
                <?php foreach ($horarios as $h): ?>
                    <tr>
                        <td class="dia"><?= $nombresDias[(int) $h['dia_semana']] ?></td>
                        <td>
                            <?php if ((int) $h['cerrado'] === 1): ?>
                                Cerrado
                            <?php else: ?>
                                <?= htmlspecialchars(substr((string) $h['hora_apertura'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                                a
                                <?= htmlspecialchars(substr((string) $h['hora_cierre'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p><?= Vista::pendiente('horario de atención') ?></p>
        <?php endif; ?>

        <p style="margin-top:24px;font-size:0.8rem;opacity:0.8;">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</footer>

<?php if ($whatsappNumero !== ''): ?>
    <a class="whatsapp-flotante"
       href="<?= htmlspecialchars(WhatsApp::enlace($whatsappNumero, "Hola, escribo desde el sitio web de {$nombreSitio}."), ENT_QUOTES, 'UTF-8') ?>"
       target="_blank" rel="noopener" aria-label="Escribir por WhatsApp">💬</a>
<?php endif; ?>

<script src="/assets/js/sitio.js"></script>
</body>
</html>
