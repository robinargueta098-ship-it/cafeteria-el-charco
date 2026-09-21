<?php
/**
 * @var array<int,array<string,mixed>> $fotos
 * @var int $totalActivas
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$total = count($fotos);
?>

<div class="panel-encabezado">
    <h1>Galería</h1>
    <a class="btn" href="/admin/galeria/crear">+ Subir fotos</a>
</div>

<?php if ($totalActivas < 6): ?>
    <div class="alerta alerta-error" style="background:#fdf1cf;color:#7a5b00;border-color:#d9b84a;">
        Hay <?= (int) $totalActivas ?> foto(s) activa(s) en el sitio público. Se recomienda tener al menos 6
        para que el carrusel de la galería luzca completo.
    </div>
<?php endif; ?>

<div class="tarjeta tabla-contenedor">
    <?php if ($fotos === []): ?>
        <p class="tabla-vacio">Todavía no hay fotos en la galería.</p>
    <?php else: ?>
        <p class="campo-ayuda" style="margin-top:0;">
            El orden de esta lista es el orden del carrusel en el sitio público. Usa ▲ ▼ para moverlas.
        </p>
        <table class="tabla tabla-apilada tabla-apilada-galeria" role="table">
            <thead role="rowgroup"><tr role="row"><th role="columnheader">#</th><th role="columnheader">Foto</th><th role="columnheader">Título</th><th role="columnheader">Estado</th><th role="columnheader">Orden</th><th role="columnheader">Acciones</th></tr></thead>
            <tbody role="rowgroup">
                <?php foreach ($fotos as $indice => $f): ?>
                    <tr role="row">
                        <td class="c-num" role="cell"><?= $indice + 1 ?></td>
                        <td class="c-img" role="cell"><img class="miniatura" src="<?= UPLOADS_URL ?>/galeria/<?= htmlspecialchars($f['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt=""></td>
                        <td class="c-titulo" role="cell"><?= htmlspecialchars((string) ($f['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="c-estado" role="cell">
                            <?php if ((int) $f['activo'] === 1): ?>
                                <span class="insignia insignia-activo">Activa</span>
                            <?php else: ?>
                                <span class="insignia insignia-inactivo">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td class="c-orden" role="cell">
                            <div class="acciones-tabla">
                                <?php foreach (['subir' => ['▲', 'Subir', $indice === 0], 'bajar' => ['▼', 'Bajar', $indice === $total - 1]] as $direccion => [$simbolo, $etiqueta, $deshabilitado]): ?>
                                    <form method="post" action="/admin/galeria/<?= (int) $f['id'] ?>/mover">
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="direccion" value="<?= $direccion ?>">
                                        <button type="submit" class="btn btn-secundario btn-pequeno"
                                                aria-label="<?= $etiqueta ?> foto <?= $indice + 1 ?>"
                                                <?= $deshabilitado ? 'disabled' : '' ?>><?= $simbolo ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="acciones-tabla c-acciones" role="cell">
                            <a class="btn btn-pequeno" href="/admin/galeria/<?= (int) $f['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/galeria/<?= (int) $f['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar esta foto? No se puede deshacer.');">
                                <?= Csrf::campo() ?>
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
