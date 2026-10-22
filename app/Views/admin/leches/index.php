<?php
/**
 * @var array<int,array<string,mixed>> $leches
 * @var array<string,array<int,array<string,mixed>>> $porCategoria
 * @var array<int,int> $conLeche
 */
use App\Core\Csrf;
use App\Core\Permisos;

require APP_PATH . '/Views/layouts/panel_header.php';

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$puedeEditar = Permisos::puede('leches', 'editar');
?>

<div class="panel-encabezado">
    <h1>Leches</h1>
    <?php if ($puedeEditar): ?><a class="btn" href="/admin/leches/crear">+ Nueva leche</a><?php endif; ?>
</div>

<p class="campo-ayuda" style="margin-top:0;">
    Son las leches que el cliente elige en el carrito para las bebidas que la permiten. «Extra» es lo que se suma al precio
    de la bebida (Q0.00 = sin costo). Si cambias un precio, se refleja de inmediato en el sitio y en los pedidos nuevos;
    los pedidos ya hechos conservan el precio con que se guardaron. Una leche desactivada deja de ofrecerse.
</p>

<div class="tarjeta tabla-contenedor" style="margin-bottom:20px;">
    <?php if ($leches === []): ?>
        <p class="tabla-vacio">Todavía no hay leches. Agrega la primera con «+ Nueva leche».</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Orden</th><th>Leche</th><th>Extra</th><th>Estado</th><?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?></tr></thead>
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

<form method="post" action="/admin/leches/productos" class="tarjeta">
    <?= Csrf::campo() ?>
    <h2 style="margin-top:0;">¿En qué productos se puede elegir la leche?</h2>
    <p class="campo-ayuda" style="margin-top:0;">
        Marca solo las bebidas que llevan leche (café con leche, lattes, chocolate…). El cliente verá la lista de leches
        en esos productos y el extra se suma al total. También puedes cambiarlo desde la ficha de cada producto.
    </p>

    <?php foreach ($porCategoria as $categoria => $productos): ?>
        <fieldset style="border:1px solid #e5d9c5;border-radius:10px;padding:12px 14px;margin:0 0 12px;">
            <legend style="font-weight:700;padding:0 6px;"><?= $e($categoria) ?></legend>
            <?php if ($puedeEditar): ?>
                <label style="display:inline-flex;align-items:center;gap:6px;margin-bottom:6px;font-size:0.9rem;color:#6b5a4d;">
                    <input type="checkbox" data-marcar-categoria> Marcar toda la categoría
                </label>
            <?php endif; ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px 22px;">
                <?php foreach ($productos as $p): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;<?= (int) $p['activo'] === 1 ? '' : 'opacity:.55;' ?>">
                        <input type="checkbox" name="producto[<?= (int) $p['id'] ?>]" value="1" <?= isset($conLeche[(int) $p['id']]) ? 'checked' : '' ?> <?= $puedeEditar ? '' : 'disabled' ?>>
                        <?= $e($p['nombre']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php if ($puedeEditar): ?>
        <button type="submit" class="btn">Guardar productos con leche</button>
    <?php endif; ?>
</form>

<script>
    // «Marcar toda la categoría»
    document.querySelectorAll('[data-marcar-categoria]').forEach(function (maestra) {
        maestra.addEventListener('change', function () {
            maestra.closest('fieldset').querySelectorAll('input[name^="producto["]').forEach(function (c) { c.checked = maestra.checked; });
        });
    });
</script>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
