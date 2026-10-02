        </main>
    </div>
</div>
<?php
// Borrador de un formulario que se rechazó por token vencido o sesión expirada (ver BorradorFormulario):
// admin.js lo vuelve a poner en los campos de la pantalla.
$borradorFormulario = \App\Core\BorradorFormulario::tomarParaRuta((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
if ($borradorFormulario !== null): ?>
<script>window.__BORRADOR = <?= json_encode($borradorFormulario, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= htmlspecialchars(\App\Core\Vista::asset('/assets/js/admin.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
