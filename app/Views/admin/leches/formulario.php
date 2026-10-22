<?php
/**
 * @var array<string,mixed>|null $leche
 * @var array<int,string> $errores
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $leche !== null && isset($leche['id']);
$accion = $esEdicion ? '/admin/leches/' . (int) $leche['id'] . '/editar' : '/admin/leches/crear';
$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
?>

<h1><?= $esEdicion ? 'Editar leche' : 'Nueva leche' ?></h1>

<div class="tarjeta" style="max-width:560px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= $e($accion) ?>">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="40" required placeholder="Ej. Avena"
                   value="<?= $e($leche['nombre'] ?? '') ?>">
        </div>

        <div class="campo">
            <label for="precio_extra">Extra (Q) que se suma al precio de la bebida</label>
            <input type="text" id="precio_extra" name="precio_extra" inputmode="decimal" maxlength="9" placeholder="0.00"
                   value="<?= $e(isset($leche['precio_extra']) ? (is_numeric($leche['precio_extra']) ? number_format((float) $leche['precio_extra'], 2, '.', '') : $leche['precio_extra']) : '0.00') ?>">
            <span class="campo-ayuda">Pon 0 si no cuesta más. Si algún día sube el precio de esta leche, cámbialo aquí: se refleja de inmediato en el sitio.</span>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1" <?= (!$esEdicion || (int) ($leche['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activa (se ofrece en el sitio)</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Agregar leche' ?></button>
        <a href="/admin/leches" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
