<?php
/**
 * @var string $codigo
 * @var string $telefono
 * @var array<string,mixed>|null $recompensa
 * @var string|null $error
 * @var array<int,array<string,mixed>> $productos
 */
use App\Core\Csrf;
use App\Core\Vista;

require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>Canjear recompensa</h1>
<p>
    Escanea el código QR que el cliente muestra en su cuenta (<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>/cuenta)
    o escribe el código a mano. En los dos casos se muestra a quién pertenece antes de canjear.
</p>

<?php if ($error !== null): ?>
    <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="canje-columnas">
    <div class="tarjeta canje-tarjeta">
        <h2 style="margin-top:0;">Escanear con la cámara</h2>
        <div id="lector-qr" class="lector-qr" hidden></div>
        <p id="lector-mensaje" class="campo-ayuda" role="status" aria-live="polite">
            Funciona con la cámara de la computadora o la del celular.
        </p>
        <button type="button" class="btn btn-verde" id="boton-escanear">Escanear código</button>
    </div>

    <div class="tarjeta canje-tarjeta">
        <h2 style="margin-top:0;">O escribir el código</h2>
        <p class="campo-ayuda">Úsalo si la cámara falla o hay poca luz.</p>
        <form method="post" action="/admin/lealtad/canjear/consultar" id="formulario-codigo">
            <?= Csrf::campo() ?>
            <div class="campo">
                <label for="codigo">Código de la recompensa</label>
                <input type="text" id="codigo" name="codigo" maxlength="20" required autofocus
                       autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX"
                       class="campo-codigo"
                       value="<?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="campo">
                <label for="telefono">Teléfono del cliente <span class="campo-ayuda">(opcional, para doble verificación)</span></label>
                <input type="tel" id="telefono" name="telefono" maxlength="20"
                       value="<?= htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn">Buscar código</button>
        </form>
    </div>
</div>

<?php if ($recompensa !== null): ?>
    <div class="tarjeta" style="max-width:480px;margin-top:24px;">
        <h2 style="margin-top:0;">Recompensa encontrada</h2>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($recompensa['cliente_nombre'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Teléfono:</strong> <?= htmlspecialchars($recompensa['cliente_telefono'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Código:</strong> <?= htmlspecialchars($recompensa['codigo'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Estado:</strong> <?= htmlspecialchars($recompensa['estado'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Generada:</strong> <?= htmlspecialchars(substr($recompensa['fecha_generada'], 0, 16), ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($recompensa['fecha_vencimiento'] !== null): ?>
            <p><strong>Vence:</strong> <?= htmlspecialchars(substr($recompensa['fecha_vencimiento'], 0, 10), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($recompensa['estado'] === 'disponible'): ?>
            <form method="post" action="/admin/lealtad/canjear/confirmar">
                <?= Csrf::campo() ?>
                <input type="hidden" name="codigo" value="<?= htmlspecialchars($recompensa['codigo'], ENT_QUOTES, 'UTF-8') ?>">
                <?php /* El servidor vuelve a comprobar, con la fila bloqueada, que este cliente sea el dueño del código. */ ?>
                <input type="hidden" name="cliente_id" value="<?= (int) $recompensa['cliente_id'] ?>">
                <div class="campo">
                    <label for="producto_id">Café gratis a entregar</label>
                    <select id="producto_id" name="producto_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-verde"
                        onclick="return confirm('¿Confirmar el canje para <?= htmlspecialchars(addslashes($recompensa['cliente_nombre']), ENT_QUOTES, 'UTF-8') ?>? No se puede deshacer.');">
                    Confirmar canje
                </button>
            </form>
        <?php else: ?>
            <p class="campo-ayuda">
                Esta recompensa ya no se puede canjear (estado: <?= htmlspecialchars($recompensa['estado'], ENT_QUOTES, 'UTF-8') ?><?= $recompensa['fecha_canjeada'] !== null ? ', usada el ' . htmlspecialchars(substr($recompensa['fecha_canjeada'], 0, 16), ENT_QUOTES, 'UTF-8') : '' ?>).
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="<?= htmlspecialchars(Vista::asset('/assets/vendor/html5-qrcode/html5-qrcode.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(Vista::asset('/assets/js/canje.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
