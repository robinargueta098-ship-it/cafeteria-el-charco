<?php
/** @var array<int,array<string,mixed>> $productos */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<div class="panel-encabezado">
    <h1>Productos</h1>
    <a class="btn" href="/admin/productos/crear">+ Nuevo producto</a>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($productos === []): ?>
        <p class="tabla-vacio">Todavía no hay productos registrados.</p>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr>
                    <th>Foto</th><th>Nombre</th><th>Categoría</th><th>Precio</th>
                    <th>Destacado</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['imagen'])): ?>
                                <img class="miniatura" src="<?= UPLOADS_URL ?>/productos/<?= htmlspecialchars($p['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span class="miniatura" aria-hidden="true"></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['categoria_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            Q<?= number_format((float) $p['precio'], 2) ?>
                            <?php if ($p['precio_alt'] !== null): ?>
                                <br><small>Q<?= number_format((float) $p['precio_alt'], 2) ?> (<?= htmlspecialchars($p['etiqueta_precio_alt'], ENT_QUOTES, 'UTF-8') ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= (int) $p['es_destacado'] === 1 ? '⭐' : '' ?>
                            <?= (int) $p['es_mas_vendido'] === 1 ? '🔥' : '' ?>
                        </td>
                        <td>
                            <?php if ((int) $p['activo'] === 1): ?>
                                <span class="insignia insignia-activo">Activo</span>
                            <?php else: ?>
                                <span class="insignia insignia-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/productos/<?= (int) $p['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/productos/<?= (int) $p['id'] ?>/estado">
                                <?= \App\Core\Csrf::campo() ?>
                                <button type="submit" class="btn btn-secundario btn-pequeno">
                                    <?= (int) $p['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="post" action="/admin/productos/<?= (int) $p['id'] ?>/eliminar"
                                  onsubmit="return confirm('¿Eliminar este producto? No se puede deshacer. Los pedidos ya hechos conservan su nombre y su precio. Si solo quieres que deje de verse en el sitio, mejor desactívalo.');">
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

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
