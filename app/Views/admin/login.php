<?php
/**
 * @var string|null $error
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/header_admin.php';
?>

<div class="tarjeta">
    <h1>Panel administrativo</h1>

    <?php if ($error !== null): ?>
        <p class="mensaje-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autofocus>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" class="btn">Ingresar</button>
    </form>
</div>

<?php
require APP_PATH . '/Views/layouts/footer_admin.php';
