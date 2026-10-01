<?php
/**
 * @var string|null $error
 * @var array<string,string> $valores
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/header_publico.php';
?>

<section class="sitio-seccion" style="min-height:60vh;">
    <div class="sitio-contenedor" style="max-width:480px;">
        <h1 style="text-align:center;">Crear mi cuenta</h1>
        <p class="sitio-seccion-intro">
            Solo para ver tu progreso de lealtad — sin correo, sin verificación.
        </p>

        <div class="tarjeta">
            <?php if ($error !== null): ?>
                <div class="alerta alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="/cuenta/registro">
                <?= Csrf::campo() ?>

                <div class="campo">
                    <label for="nombre">Tu nombre</label>
                    <input type="text" id="nombre" name="nombre" maxlength="120" required
                           value="<?= htmlspecialchars($valores['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="campo">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" maxlength="8" minlength="8" required
                           inputmode="numeric" pattern="[0-9]{8}" autocomplete="tel-national" data-solo-digitos
                           title="8 dígitos, solo números" placeholder="12345678"
                           value="<?= htmlspecialchars($valores['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="campo">
                    <label for="pin">Crea un PIN de 4 dígitos</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required>
                </div>

                <div class="campo">
                    <label for="confirmar_pin">Confirma el PIN</label>
                    <input type="password" id="confirmar_pin" name="confirmar_pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required>
                </div>

                <button type="submit" class="btn" style="width:100%;">Crear cuenta</button>
            </form>

            <p style="text-align:center;margin-top:16px;">
                ¿Ya tienes cuenta? <a href="/cuenta/login">Inicia sesión</a>
            </p>
        </div>
    </div>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
