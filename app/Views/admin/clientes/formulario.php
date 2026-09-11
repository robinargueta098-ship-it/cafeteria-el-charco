<?php
/**
 * @var array<string,mixed>|null $cliente
 * @var array<int,string> $errores
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $cliente !== null && isset($cliente['id']);
$accion = $esEdicion ? '/admin/clientes/' . (int) $cliente['id'] . '/editar' : '/admin/clientes/crear';
?>

<h1><?= $esEdicion ? 'Editar cliente' : 'Nuevo cliente' ?></h1>

<div class="tarjeta" style="max-width:640px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($accion, ENT_QUOTES, 'UTF-8') ?>">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="120" required
                   value="<?= htmlspecialchars((string) ($cliente['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" maxlength="20" required
                   value="<?= htmlspecialchars((string) ($cliente['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="email">Correo (opcional)</label>
            <input type="email" id="email" name="email" maxlength="150"
                   value="<?= htmlspecialchars((string) ($cliente['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="notas">Notas (opcional)</label>
            <input type="text" id="notas" name="notas" maxlength="255"
                   value="<?= htmlspecialchars((string) ($cliente['notas'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear cliente' ?></button>
        <a href="/admin/clientes" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
