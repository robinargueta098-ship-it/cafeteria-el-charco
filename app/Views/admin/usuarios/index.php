<?php
/** @var array<int,array<string,mixed>> $usuarios */
use App\Core\Auth;

require APP_PATH . '/Views/layouts/panel_header.php';
$actual = Auth::usuario();
?>

<div class="panel-encabezado">
    <h1>Usuarios</h1>
    <a class="btn" href="/admin/usuarios/crear">+ Nuevo usuario</a>
</div>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($actual !== null && $actual['id'] === (int) $u['id']): ?><em>(tú)</em><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['rol_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ((int) $u['activo'] === 1): ?>
                            <span class="insignia insignia-activo">Activo</span>
                        <?php else: ?>
                            <span class="insignia insignia-inactivo">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($u['ultimo_acceso'] ?? 'Nunca'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="acciones-tabla">
                        <a class="btn btn-pequeno" href="/admin/usuarios/<?= (int) $u['id'] ?>/editar">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
