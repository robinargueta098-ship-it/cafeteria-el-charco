<?php
/**
 * @var array<int,array<string,mixed>> $clientes
 * @var string $termino
 */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>Lealtad</h1>

<div class="tarjeta" style="margin-bottom:16px;">
    <form method="get" action="/admin/lealtad">
        <div class="campo campo-busqueda" style="margin-bottom:0;display:flex;gap:8px;">
            <input type="text" name="q" placeholder="Busca al cliente por nombre o teléfono..." autofocus
                   value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn" style="white-space:nowrap;">Buscar</button>
        </div>
    </form>
</div>

<?php if ($termino !== ''): ?>
    <div class="tarjeta tabla-contenedor">
        <?php if ($clientes === []): ?>
            <p class="tabla-vacio">No se encontraron clientes. <a href="/admin/clientes/crear">Registrar uno nuevo</a>.</p>
        <?php else: ?>
            <table class="tabla">
                <thead><tr><th>Nombre</th><th>Teléfono</th><th>Cafés acumulados</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($c['telefono'], ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $p = \App\Models\Cliente::progresoTarjeta($c, \App\Services\LealtadService::obtenerUmbral()); ?>
                            <td>
                                <?= $p['mostrado'] ?> / <?= $p['total'] ?>
                                <?php if ($p['disponibles'] > 0): ?><span class="insignia insignia-activo"><?= \App\Models\Cliente::etiquetaGratis($p['disponibles']) ?></span><?php endif; ?>
                            </td>
                            <td><a class="btn btn-pequeno" href="/admin/lealtad/<?= (int) $c['id'] ?>">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
