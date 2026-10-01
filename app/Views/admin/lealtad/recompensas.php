<?php
/**
 * @var array<int,array<string,mixed>> $canjes
 * @var string $estadoFiltro
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$etiquetasEstado = ['disponible' => 'Disponible', 'canjeada' => 'Canjeada', 'cancelada' => 'Cancelada', 'vencida' => 'Vencida'];
?>

<div class="panel-encabezado">
    <h1>Recompensas</h1>
    <a href="/admin/lealtad/canjear" class="btn">Canjear por código</a>
</div>

<div class="tarjeta" style="margin-bottom:16px;">
    <form method="get" action="/admin/recompensas" class="form-filtros" style="display:flex;gap:12px;align-items:flex-end;">
        <div class="campo" style="margin-bottom:0;">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach ($etiquetasEstado as $valor => $etiqueta): ?>
                    <option value="<?= $valor ?>" <?= $estadoFiltro === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Filtrar</button>
    </form>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($canjes === []): ?>
        <p class="tabla-vacio">No hay recompensas con ese filtro.</p>
    <?php else: ?>
        <table class="tabla tabla-primera-sin-corte">
            <thead><tr><th>Código</th><th>Cliente</th><th>Estado</th><th>Generada</th><th>Café entregado</th></tr></thead>
            <tbody>
                <?php foreach ($canjes as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="/admin/lealtad/<?= (int) $r['cliente_id'] ?>"><?= htmlspecialchars($r['cliente_nombre'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td>
                            <span class="insignia insignia-<?= $r['estado'] === 'disponible' ? 'activo' : 'inactivo' ?>">
                                <?= $etiquetasEstado[$r['estado']] ?? $r['estado'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(substr($r['fecha_generada'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['producto_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
