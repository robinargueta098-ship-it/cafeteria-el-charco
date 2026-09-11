<?php
/**
 * @var array<int,array<string,mixed>> $categorias
 */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<div class="panel-encabezado">
    <h1>Categorías</h1>
    <a class="btn" href="/admin/categorias/crear">+ Nueva categoría</a>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($categorias === []): ?>
        <p class="tabla-vacio">Todavía no hay categorías registradas.</p>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Nombre</th>
                    <th>Notas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td><?= (int) $c['orden'] ?></td>
                        <td><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($c['notas'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $c['activo'] === 1): ?>
                                <span class="insignia insignia-activo">Activa</span>
                            <?php else: ?>
                                <span class="insignia insignia-inactivo">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/categorias/<?= (int) $c['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/categorias/<?= (int) $c['id'] ?>/eliminar"
                                  onsubmit="return confirm('¿Eliminar esta categoría? Solo se puede si no tiene productos asociados.');">
                                <?= \App\Core\Csrf::campo() ?>
                                <button type="submit" class="btn btn-peligro btn-pequeno">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require APP_PATH . '/Views/layouts/panel_footer.php';
