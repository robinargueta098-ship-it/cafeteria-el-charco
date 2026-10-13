<?php
/** @var array<int,array<string,mixed>> $promociones */
require APP_PATH . '/Views/layouts/panel_header.php';

$etiquetasTipo = [
    'descuento_porcentaje' => 'Descuento %',
    'descuento_monto'      => 'Descuento fijo',
    'dos_por_uno'          => '2x1',
    'producto_gratis'      => 'Producto gratis',
];
$hoy = date('Y-m-d');
?>

<div class="panel-encabezado">
    <h1>Promociones</h1>
    <a class="btn" href="/admin/promociones/crear">+ Nueva promoción</a>
</div>

<p class="campo-ayuda" style="margin-top:0;">El orden de esta lista es el orden en que se muestran en el sitio (la de arriba aparece primero). Usa ▲ ▼ para moverlas.</p>

<div class="tarjeta tabla-contenedor">
    <?php if ($promociones === []): ?>
        <p class="tabla-vacio">Todavía no hay promociones registradas.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th></th><th>Nombre</th><th>Tipo</th><th>Alcance</th><th>Vigencia</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php $total = count($promociones); ?>
                <?php foreach ($promociones as $indice => $p): ?>
                    <?php
                        $vigente = (int) $p['activo'] === 1 && $hoy >= $p['fecha_inicio'] && $hoy <= $p['fecha_fin'];
                        $futura = $hoy < $p['fecha_inicio'];
                        $alcance = $p['producto_nombre'] ?? $p['categoria_nombre'] ?? 'Todo el menú';
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['imagen'])): ?>
                                <img class="miniatura" src="<?= UPLOADS_URL ?>/promociones/<?= htmlspecialchars($p['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span class="miniatura" aria-hidden="true"></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars($etiquetasTipo[$p['tipo']] ?? $p['tipo'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($p['valor'] !== null): ?>
                                (<?= $p['tipo'] === 'descuento_porcentaje' ? (int) $p['valor'] . '%' : 'Q' . number_format((float) $p['valor'], 2) ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($alcance, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['fecha_inicio'], ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars($p['fecha_fin'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $p['activo'] === 0): ?>
                                <span class="insignia insignia-inactivo">Inactiva</span>
                            <?php elseif ($vigente): ?>
                                <span class="insignia insignia-vigente">Vigente</span>
                            <?php elseif ($futura): ?>
                                <span class="insignia insignia-programada">Programada</span>
                            <?php else: ?>
                                <span class="insignia insignia-vencida">Vencida</span>
                            <?php endif; ?>
                        </td>
                        <td class="c-orden">
                            <div class="acciones-tabla">
                                <?php foreach (['subir' => ['▲', 'Subir', $indice === 0], 'bajar' => ['▼', 'Bajar', $indice === $total - 1]] as $direccion => [$simbolo, $etiqueta, $deshabilitado]): ?>
                                    <form method="post" action="/admin/promociones/<?= (int) $p['id'] ?>/mover">
                                        <?= \App\Core\Csrf::campo() ?>
                                        <input type="hidden" name="direccion" value="<?= $direccion ?>">
                                        <button type="submit" class="btn btn-secundario btn-pequeno"
                                                aria-label="<?= $etiqueta ?> promoción <?= $indice + 1 ?>"
                                                <?= $deshabilitado ? 'disabled' : '' ?>><?= $simbolo ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/promociones/<?= (int) $p['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/promociones/<?= (int) $p['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar esta promoción?');">
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
