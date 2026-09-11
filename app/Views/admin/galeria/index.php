<?php
/** @var array<int,array<string,mixed>> $fotos */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<div class="panel-encabezado">
    <h1>Galería</h1>
    <a class="btn" href="/admin/galeria/crear">+ Subir foto</a>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($fotos === []): ?>
        <p class="tabla-vacio">Todavía no hay fotos en la galería.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Foto</th><th>Título</th><th>Orden</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($fotos as $f): ?>
                    <tr>
                        <td><img class="miniatura" src="<?= UPLOADS_URL ?>/galeria/<?= htmlspecialchars($f['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt=""></td>
                        <td><?= htmlspecialchars((string) ($f['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $f['orden'] ?></td>
                        <td>
                            <?php if ((int) $f['activo'] === 1): ?>
                                <span class="insignia insignia-activo">Activa</span>
                            <?php else: ?>
                                <span class="insignia insignia-inactivo">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/galeria/<?= (int) $f['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/galeria/<?= (int) $f['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar esta foto?');">
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
