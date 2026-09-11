<?php
/** @var array<string,string> $config */
$whatsapp = $config['whatsapp_numero'] ?? '';
?>
</main>
<footer class="contenedor">
    <?php if ($whatsapp !== ''): ?>
        <p>
            WhatsApp:
            <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $whatsapp), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>
    <?php endif; ?>
</footer>
</body>
</html>
