<?php
/**
 * @var array<int,array<string,mixed>> $clientes
 * @var string $termino
 */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<div class="panel-encabezado">
    <h1>Clientes</h1>
    <a class="btn" href="/admin/clientes/crear">+ Nuevo cliente</a>
</div>

<div class="tarjeta" style="margin-bottom:16px;">
    <form method="get" action="/admin/clientes">
        <div class="campo" style="margin-bottom:0;display:flex;gap:8px;">
            <input type="text" name="q" placeholder="Buscar por nombre o teléfono..."
                   value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn" style="white-space:nowrap;">Buscar</button>
        </div>
    </form>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($clientes === []): ?>
        <p class="tabla-vacio">No se encontraron clientes.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Teléfono</th><th>Cafés acumulados</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($c['telefono'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $c['cafes_acumulados'] ?></td>
                        <td class="acciones-tabla">
                            <a class="btn btn-pequeno" href="/admin/lealtad/<?= (int) $c['id'] ?>">Lealtad</a>
                            <a class="btn btn-secundario btn-pequeno" href="/admin/clientes/<?= (int) $c['id'] ?>/editar">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
