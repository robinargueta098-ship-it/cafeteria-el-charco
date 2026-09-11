<?php
/**
 * @var array<string,mixed>|null $categoria
 * @var array<int,string> $errores
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $categoria !== null && isset($categoria['id']);
$accion = $esEdicion ? '/admin/categorias/' . (int) $categoria['id'] . '/editar' : '/admin/categorias/crear';
?>

<h1><?= $esEdicion ? 'Editar categoría' : 'Nueva categoría' ?></h1>

<div class="tarjeta" style="max-width:640px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($accion, ENT_QUOTES, 'UTF-8') ?>">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required
                   value="<?= htmlspecialchars((string) ($categoria['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="descripcion">Descripción</label>
            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                   value="<?= htmlspecialchars((string) ($categoria['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="notas">Notas (texto libre, visible en el sitio público)</label>
            <input type="text" id="notas" name="notas" maxlength="255"
                   placeholder="Ej. Leches disponibles: entera +Q0, deslactosada +Q2, almendras +Q3"
                   value="<?= htmlspecialchars((string) ($categoria['notas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="orden">Orden</label>
            <input type="number" id="orden" name="orden" min="0"
                   value="<?= (int) ($categoria['orden'] ?? 0) ?>">
            <span class="campo-ayuda">Los números más bajos aparecen primero en el menú.</span>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1"
                   <?= (!$esEdicion || (int) ($categoria['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activa (visible en el sitio público)</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear categoría' ?></button>
        <a href="/admin/categorias" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php
require APP_PATH . '/Views/layouts/panel_footer.php';
