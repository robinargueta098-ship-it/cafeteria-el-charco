<?php
/**
 * @var string|null $error
 * @var int $segundos segundos de bloqueo que faltan (0 = sin bloqueo); la pantalla los va descontando
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/header_publico.php';
?>

<section class="sitio-seccion" style="min-height:60vh;">
    <div class="sitio-contenedor" style="max-width:480px;">
        <h1 style="text-align:center;">Mi cuenta</h1>
        <p class="sitio-seccion-intro">Consulta tu progreso de lealtad.</p>

        <div class="tarjeta">
            <?php if ($segundos > 0): ?>
                <div class="alerta alerta-error" id="bloqueo-aviso" role="status" data-segundos="<?= (int) $segundos ?>">
                    Demasiados intentos fallidos. Intenta de nuevo en <strong id="bloqueo-segundos"><?= (int) $segundos ?></strong>
                    <span id="bloqueo-unidad">segundos</span> o acércate a la cafetería para que te ayudemos.
                </div>
            <?php elseif ($error !== null): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/cuenta/login">
                <?= Csrf::campo() ?>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="8" required autofocus
                           inputmode="numeric" pattern="[0-9]{8}" autocomplete="tel-national" data-solo-digitos
                           title="8 dígitos, solo números" placeholder="12345678">
                </div>

                <div class="campo">
                    <label for="pin">PIN</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required>
                </div>

                <button type="submit" class="btn" id="boton-entrar" style="width:100%;"<?= $segundos > 0 ? ' disabled' : '' ?>>Entrar</button>
            </form>

            <?php /* Solo después de más de 5 fallidos seguidos; no es enlace ni botón: se resuelve en persona. */ ?>
            <?php if (!empty($_SESSION['cliente_login_ayuda'])): ?>
                <p style="text-align:center;margin-top:16px;">
                    ¿Olvidaste tu PIN? Acércate a la cafetería y te ayudamos a restablecerlo.
                </p>
            <?php endif; ?>

            <p style="text-align:center;margin-top:16px;">
                ¿Todavía no tienes cuenta? <a href="/cuenta/registro">Créala aquí</a>
            </p>
        </div>
    </div>
</section>

<?php if ($segundos > 0): ?>
<script>
(function () {
    var aviso = document.getElementById('bloqueo-aviso');
    var numero = document.getElementById('bloqueo-segundos');
    var unidad = document.getElementById('bloqueo-unidad');
    var boton = document.getElementById('boton-entrar');
    // Se calcula con el reloj (no restando de uno en uno) para que no se atrase si la pestaña queda en segundo plano.
    var fin = Date.now() + parseInt(aviso.getAttribute('data-segundos'), 10) * 1000;
    var reloj;
    function pintar() {
        var falta = Math.max(0, Math.ceil((fin - Date.now()) / 1000));
        if (falta > 0) {
            numero.textContent = falta;
            unidad.textContent = falta === 1 ? 'segundo' : 'segundos';
            return;
        }
        window.clearInterval(reloj);
        aviso.className = 'alerta alerta-exito';
        aviso.textContent = 'Ya puedes intentar de nuevo.';
        boton.disabled = false;
        document.getElementById('pin').focus();
    }
    reloj = window.setInterval(pintar, 250);
    pintar();
})();
</script>
<?php endif; ?>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
