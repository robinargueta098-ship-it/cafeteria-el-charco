<?php
/**
 * @var array<int,array<string,mixed>> $leches
 * @var array<string,array<int,array<string,mixed>>> $porCategoria
 * @var array<int,int> $conLeche
 * @var array<int,array<int,int>> $listasOpcion productos de cada opción restringida (id de opción => ids)
 */
use App\Core\Csrf;
use App\Core\Permisos;

require APP_PATH . '/Views/layouts/panel_header.php';

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$puedeEditar = Permisos::puede('leches', 'editar');
?>

<div class="panel-encabezado">
    <h1>Leches y agua</h1>
    <?php if ($puedeEditar): ?><a class="btn" href="/admin/leches/crear">+ Nueva leche</a><?php endif; ?>
</div>

<p class="campo-ayuda" style="margin-top:0;">
    Son las opciones de «Preparar con» que el cliente elige en el carrito (leches, agua…). «Extra» es lo que se suma al precio
    de la bebida (Q0.00 = sin costo). Si cambias un precio, se refleja de inmediato en el sitio y en los pedidos nuevos;
    los pedidos ya hechos conservan el precio con que se guardaron. Una leche desactivada deja de ofrecerse.
</p>

<div class="tarjeta tabla-contenedor" style="margin-bottom:20px;">
    <?php if ($leches === []): ?>
        <p class="tabla-vacio">Todavía no hay leches. Agrega la primera con «+ Nueva leche».</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Orden</th><th>Opción</th><th>Extra</th><th>Se ofrece en</th><th>Estado</th><?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($leches as $i => $l): ?>
                    <tr>
                        <td>
                            <?php if ($puedeEditar): ?>
                                <form method="post" action="/admin/leches/<?= (int) $l['id'] ?>/mover" style="display:inline;">
                                    <?= Csrf::campo() ?><input type="hidden" name="direccion" value="subir">
                                    <button type="submit" class="btn btn-pequeno btn-secundario" aria-label="Subir" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
                                </form>
                                <form method="post" action="/admin/leches/<?= (int) $l['id'] ?>/mover" style="display:inline;">
                                    <?= Csrf::campo() ?><input type="hidden" name="direccion" value="bajar">
                                    <button type="submit" class="btn btn-pequeno btn-secundario" aria-label="Bajar" <?= $i === count($leches) - 1 ? 'disabled' : '' ?>>▼</button>
                                </form>
                            <?php else: ?>
                                <?= $i + 1 ?>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= $e($l['nombre']) ?></strong></td>
                        <td><?= (float) $l['precio_extra'] > 0 ? '+Q' . number_format((float) $l['precio_extra'], 2) : 'Sin costo' ?></td>
                        <td>
                            <?php if ((int) $l['solo_productos'] === 1): ?>
                                <?= count($listasOpcion[(int) $l['id']] ?? []) ?> producto(s) elegidos
                            <?php else: ?>
                                Todos los productos con leche
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $l['activo'] === 1): ?>
                                <span class="insignia insignia-activo">Activa</span>
                            <?php else: ?>
                                <span class="insignia insignia-inactivo">Desactivada</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($puedeEditar): ?>
                            <td class="acciones-tabla">
                                <a class="btn btn-pequeno" href="/admin/leches/<?= (int) $l['id'] ?>/editar">Editar</a>
                                <form method="post" action="/admin/leches/<?= (int) $l['id'] ?>/estado">
                                    <?= Csrf::campo() ?>
                                    <button type="submit" class="btn btn-pequeno btn-secundario"><?= (int) $l['activo'] === 1 ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                                <form method="post" action="/admin/leches/<?= (int) $l['id'] ?>/eliminar"
                                      onsubmit="return confirm('¿Eliminar esta leche? Los pedidos ya hechos conservan su nombre y su precio. Si solo quieres dejar de ofrecerla, mejor desactívala.');">
                                    <?= Csrf::campo() ?>
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form method="post" action="/admin/leches/productos" class="tarjeta" style="margin-bottom:20px;">
    <?= Csrf::campo() ?>
    <h2 style="margin-top:0;">¿Qué se ofrece en cada producto?</h2>
    <p class="campo-ayuda" style="margin-top:0;">
        Marca en qué bebidas el cliente puede elegir <strong>«Preparar con»</strong>. La columna <strong>Leche</strong> ofrece todas las leches;
        las demás columnas son opciones que solo se ofrecen donde las marques (por ejemplo, Agua). Con la casilla de cada
        encabezado marcas o desmarcas <strong>toda la categoría</strong>. También puedes cambiar la leche desde la ficha de cada producto.
    </p>

    <?php
    $restringidas = array_values(array_filter($leches, function ($l) { return (int) $l['solo_productos'] === 1; }));
    ?>
    <?php foreach ($porCategoria as $categoria => $productos): ?>
        <fieldset style="border:1px solid #e5d9c5;border-radius:10px;padding:10px 14px 12px;margin:0 0 12px;">
            <legend style="font-weight:700;padding:0 6px;"><?= $e($categoria) ?></legend>
            <table class="tabla" style="margin:0;">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="text-align:center;width:110px;">
                            Leche
                            <?php if ($puedeEditar): ?><br><input type="checkbox" data-marcar-columna="leche" aria-label="Marcar toda la categoría con leche" title="Toda la categoría"><?php endif; ?>
                        </th>
                        <?php foreach ($restringidas as $opcion): ?>
                            <th style="text-align:center;width:110px;">
                                <?= $e($opcion['nombre']) ?>
                                <?php if ($puedeEditar): ?><br><input type="checkbox" data-marcar-columna="opcion-<?= (int) $opcion['id'] ?>" aria-label="Marcar toda la categoría con <?= $e($opcion['nombre']) ?>" title="Toda la categoría"><?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <tr style="<?= (int) $p['activo'] === 1 ? '' : 'opacity:.55;' ?>">
                            <td><?= $e($p['nombre']) ?></td>
                            <td style="text-align:center;">
                                <input type="checkbox" data-columna="leche" name="producto[<?= (int) $p['id'] ?>]" value="1" <?= isset($conLeche[(int) $p['id']]) ? 'checked' : '' ?> <?= $puedeEditar ? '' : 'disabled' ?> aria-label="Leche en <?= $e($p['nombre']) ?>">
                            </td>
                            <?php foreach ($restringidas as $opcion): ?>
                                <td style="text-align:center;">
                                    <input type="checkbox" data-columna="opcion-<?= (int) $opcion['id'] ?>" name="opcion[<?= (int) $opcion['id'] ?>][<?= (int) $p['id'] ?>]" value="1" <?= isset($listasOpcion[(int) $opcion['id']][(int) $p['id']]) ? 'checked' : '' ?> <?= $puedeEditar ? '' : 'disabled' ?> aria-label="<?= $e($opcion['nombre']) ?> en <?= $e($p['nombre']) ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($puedeEditar): ?>
        <button type="submit" class="btn">Guardar</button>
    <?php endif; ?>
</form>

<script>
    // Casilla del encabezado: marca o desmarca toda la categoría en esa columna.
    document.querySelectorAll('[data-marcar-columna]').forEach(function (maestra) {
        var columna = maestra.getAttribute('data-marcar-columna');
        var celdas = function () { return maestra.closest('fieldset').querySelectorAll('input[data-columna="' + columna + '"]'); };
        var sincronizar = function () {
            var todas = Array.prototype.every.call(celdas(), function (c) { return c.checked; });
            maestra.checked = todas;
        };
        maestra.addEventListener('change', function () {
            celdas().forEach(function (c) { c.checked = maestra.checked; });
        });
        celdas().forEach(function (c) { c.addEventListener('change', sincronizar); });
        sincronizar();
    });
</script>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
