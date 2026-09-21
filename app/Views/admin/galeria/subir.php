<?php
/**
 * @var array<int,string> $errores
 * @var int $maxArchivos
 * @var int $maxBytes
 * @var bool $optimiza si el servidor tiene GD para crear versiones reducidas
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$maxMb = (int) round($maxBytes / 1024 / 1024);
?>

<h1>Subir fotos</h1>

<div class="tarjeta" style="max-width:640px;">
    <?php if (!$optimiza): ?>
        <div class="alerta alerta-error" style="background:#fdf1cf;color:#7a5b00;border-color:#d9b84a;">
            Este servidor no tiene la extensión GD de PHP, así que las fotos se guardan tal cual, sin las
            versiones reducidas que aceleran el sitio en el celular. Sigue funcionando, solo que más pesado.
            (En Hostinger normalmente está activa.)
        </div>
    <?php endif; ?>

    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/galeria/crear" enctype="multipart/form-data" id="form-subida-galeria">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="imagenes">Fotos</label>
            <input type="file" id="imagenes" name="imagenes[]" multiple required
                   accept=".jpg,.jpeg,.png,.webp"
                   data-subida-multiple data-resumen="resumen-subida" data-boton="boton-subir"
                   data-max-archivos="<?= (int) $maxArchivos ?>"
                   data-max-bytes="<?= (int) $maxBytes ?>">
            <span class="campo-ayuda">
                Puedes elegir varias a la vez (hasta <?= (int) $maxArchivos ?>). JPG, PNG o WEBP, máximo <?= $maxMb ?> MB cada una.
                Se agregan al final de la galería; después puedes reordenarlas con las flechas
                y ponerles título con "Editar".
            </span>
            <div id="resumen-subida" class="campo-ayuda" aria-live="polite"></div>
        </div>

        <button type="submit" class="btn" id="boton-subir">Subir fotos</button>
        <a href="/admin/galeria" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
