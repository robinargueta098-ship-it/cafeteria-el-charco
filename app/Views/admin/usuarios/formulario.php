<?php
/**
 * @var array<string,mixed>|null $usuarioEditado
 * @var array<int,string> $errores
 * @var array<int,array<string,mixed>> $roles
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $usuarioEditado !== null && isset($usuarioEditado['id']);
$accion = $esEdicion ? '/admin/usuarios/' . (int) $usuarioEditado['id'] . '/editar' : '/admin/usuarios/crear';
?>

<h1><?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario' ?></h1>

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
        <?php if ($esEdicion): ?>
            <input type="hidden" name="_id_editado" value="<?= (int) $usuarioEditado['id'] ?>">
        <?php endif; ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required
                   value="<?= htmlspecialchars((string) ($usuarioEditado['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <?php $protegido = $esEdicion && \App\Models\Usuario::esPrincipal((int) ($usuarioEditado['id'] ?? 0)); ?>
        <?php if ($protegido): ?>
            <div class="alerta" style="background:#f4ecdf;border:1px solid #d9c9a8;color:#3b2415;">
                <strong>Usuario principal:</strong> por seguridad no se puede desactivar, cambiarle el rol ni cambiarle el nombre de usuario.
                Sí puedes cambiar su nombre y su contraseña.
            </div>
        <?php endif; ?>

        <div class="campo">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" maxlength="50" required<?= $protegido ? ' readonly' : '' ?>
                   value="<?= htmlspecialchars((string) ($usuarioEditado['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="password"><?= $esEdicion ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña' ?></label>
            <input type="password" id="password" name="password" minlength="8" <?= $esEdicion ? '' : 'required' ?>>
            <span class="campo-ayuda">Mínimo 8 caracteres.</span>
        </div>

        <div class="campo">
            <label for="rol_id">Rol</label>
            <select id="rol_id" name="rol_id" required<?= $protegido ? ' disabled' : '' ?>>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= (int) ($usuarioEditado['rol_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1"<?= $protegido ? ' disabled' : '' ?> <?= (!$esEdicion || (int) ($usuarioEditado['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activo</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?></button>
        <a href="/admin/usuarios" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
