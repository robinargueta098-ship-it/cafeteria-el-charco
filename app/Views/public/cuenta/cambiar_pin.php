<?php
/**
 * Cambio obligatorio de PIN: el cliente entró con un PIN temporal que le dio el personal en persona.
 *
 * @var string|null $error
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/header_publico.php';
?>

<section class="sitio-seccion" style="min-height:60vh;">
    <div class="sitio-contenedor" style="max-width:480px;">
        <h1 style="text-align:center;">Crea tu PIN nuevo</h1>
        <p class="sitio-seccion-intro">Entraste con un PIN temporal. Antes de continuar, elige un PIN nuevo de 4 dígitos que solo tú conozcas.</p>

        <div class="tarjeta">
            <?php if ($error !== null): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/cuenta/cambiar-pin" autocomplete="off">
                <?= Csrf::campo() ?>

                <div class="campo">
                    <label for="pin">PIN nuevo (4 dígitos)</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autofocus
                           autocomplete="new-password">
                </div>

                <div class="campo">
                    <label for="confirmar_pin">Confirma tu PIN nuevo</label>
                    <input type="password" id="confirmar_pin" name="confirmar_pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required
                           autocomplete="new-password">
                </div>

                <p class="campo-ayuda">Debe ser distinto al PIN temporal que te dieron.</p>

                <button type="submit" class="btn" style="width:100%;">Guardar PIN y entrar</button>
            </form>
        </div>
    </div>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
