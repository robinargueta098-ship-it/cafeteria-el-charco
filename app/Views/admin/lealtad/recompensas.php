<?php
/** @var array<int,array<string,mixed>> $canjes */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>Recompensas canjeadas</h1>

<div class="tarjeta tabla-contenedor">
    <?php if ($canjes === []): ?>
        <p class="tabla-vacio">Todavía no se ha canjeado ninguna recompensa.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Cliente</th><th>Teléfono</th><th>Café entregado</th><th>Registrado por</th></tr></thead>
            <tbody>
                <?php foreach ($canjes as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="/admin/lealtad/<?= (int) $c['cliente_id'] ?>"><?= htmlspecialchars($c['cliente_nombre'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars($c['cliente_telefono'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($c['producto_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($c['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
