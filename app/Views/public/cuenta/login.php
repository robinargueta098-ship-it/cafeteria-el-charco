<?php
/**
 * @var string|null $error
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
            <?php if ($error !== null): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/cuenta/login">
                <?= Csrf::campo() ?>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="20" required autofocus>
                </div>

                <div class="campo">
                    <label for="pin">PIN</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required>
                </div>

                <button type="submit" class="btn" style="width:100%;">Entrar</button>
            </form>

            <p style="text-align:center;margin-top:16px;">
                ¿Todavía no tienes cuenta? <a href="/cuenta/registro">Créala aquí</a>
            </p>
        </div>
    </div>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
