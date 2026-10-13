<?php
/** @var array<int,array<string,mixed>> $temporadas */
require APP_PATH . '/Views/layouts/panel_header.php';
$hoy = date('Y-m-d');
?>

<div class="panel-encabezado">
    <h1>Temporadas</h1>
    <a class="btn" href="/admin/temporadas/crear">+ Nueva temporada</a>
</div>

<p class="campo-ayuda" style="margin-top:0;">El orden de esta lista es el orden en que se muestran en el sitio. Usa ▲ ▼ para moverlas.</p>

<div class="tarjeta tabla-contenedor">
    <?php if ($temporadas === []): ?>
        <p class="tabla-vacio">Todavía no hay temporadas registradas.</p>
    <?php else: ?>
        <table class="tabla">
            <thead>
                <tr><th></th><th>Nombre</th><th>Vigencia</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php $total = count($temporadas); ?>
                <?php foreach ($temporadas as $indice => $t): ?>
                    <?php
                        $vigente = $hoy >= $t['fecha_inicio'] && $hoy <= $t['fecha_fin'];
                        $futura = $hoy < $t['fecha_inicio'];
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($t['imagen'])): ?>
                                <img class="miniatura" src="<?= UPLOADS_URL ?>/temporadas/<?= htmlspecialchars($t['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php else: ?>
                                <span class="miniatura" aria-hidden="true"></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($t['fecha_inicio'], ENT_QUOTES, 'UTF-8') ?> al <?= htmlspecialchars($t['fecha_fin'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ((int) $t['activo'] === 0): ?>
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
                                    <form method="post" action="/admin/temporadas/<?= (int) $t['id'] ?>/mover">
                                        <?= \App\Core\Csrf::campo() ?>
                                        <input type="hidden" name="direccion" value="<?= $direccion ?>">
                                        <button type="submit" class="btn btn-secundario btn-pequeno"
                                                aria-label="<?= $etiqueta ?> temporada <?= $indice + 1 ?>"
                                                <?= $deshabilitado ? 'disabled' : '' ?>><?= $simbolo ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/temporadas/<?= (int) $t['id'] ?>/editar">Editar</a>
                            <form method="post" action="/admin/temporadas/<?= (int) $t['id'] ?>/eliminar"
                                  onsubmit="return confirm('¿Eliminar esta temporada?');">
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
