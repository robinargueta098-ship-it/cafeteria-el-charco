<?php
/**
 * @var array<string,mixed>|null $foto
 * @var array<int,string> $errores
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $foto !== null && isset($foto['id']);
$accion = $esEdicion ? '/admin/galeria/' . (int) $foto['id'] . '/editar' : '/admin/galeria/crear';
?>

<h1><?= $esEdicion ? 'Editar foto' : 'Subir foto' ?></h1>

<div class="tarjeta" style="max-width:640px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($accion, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="imagen">Foto</label>
            <?php if (!empty($foto['imagen'])): ?>
                <div style="margin-bottom:8px;">
                    <img class="miniatura" src="<?= UPLOADS_URL ?>/galeria/<?= htmlspecialchars($foto['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                </div>
            <?php endif; ?>
            <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp" <?= $esEdicion ? '' : 'required' ?>>
            <span class="campo-ayuda">JPG, PNG o WEBP, máximo 5 MB.</span>
        </div>

        <div class="campo">
            <label for="titulo">Título (opcional)</label>
            <input type="text" id="titulo" name="titulo" maxlength="120"
                   value="<?= htmlspecialchars((string) ($foto['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="descripcion">Descripción (opcional)</label>
            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                   value="<?= htmlspecialchars((string) ($foto['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="orden">Orden</label>
            <input type="number" id="orden" name="orden" min="0" value="<?= (int) ($foto['orden'] ?? 0) ?>">
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1" <?= (!$esEdicion || (int) ($foto['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activa (visible en el sitio público)</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Subir foto' ?></button>
        <a href="/admin/galeria" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
