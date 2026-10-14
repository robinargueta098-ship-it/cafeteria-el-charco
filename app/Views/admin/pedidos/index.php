<?php
/**
 * @var array<string,string> $filtros
 * @var array<int,string> $avisos
 * @var array<int,array<string,mixed>> $filas
 * @var int $total
 * @var int $pagina
 * @var int $totalPaginas
 * @var int $pendientes
 */
use App\Core\Dinero;
use App\Models\Pedido;

require APP_PATH . '/Views/layouts/panel_header.php';

$consulta = function (array $extra = []) use ($filtros) {
    $p = array_filter(array_merge($filtros, $extra), function ($v) { return $v !== '' && $v !== null; });

    return '/admin/pedidos' . ($p !== [] ? '?' . http_build_query($p) : '');
};
?>

<div class="panel-encabezado">
    <h1>Pedidos</h1>
</div>

<p class="campo-ayuda" style="margin-top:0;">
    Pedidos hechos desde la página web (carrito). Los <strong>Enviados</strong> esperan que los confirmes.
    <?php if ($pendientes > 0): ?>
        <strong>Ahora hay <?= (int) $pendientes ?> sin confirmar.</strong>
    <?php endif; ?>
</p>

<?php foreach ($avisos as $aviso): ?>
    <div class="alerta alerta-error"><?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?></div>
<?php endforeach; ?>

<div class="tarjeta" style="margin-bottom:16px;">
    <form method="get" action="/admin/pedidos" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="campo" style="margin-bottom:0;">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (Pedido::ETIQUETAS_ESTADO as $clave => $etiqueta): ?>
                    <option value="<?= $clave ?>" <?= $filtros['estado'] === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo" style="margin-bottom:0;">
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($filtros['desde'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo" style="margin-bottom:0;">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($filtros['hasta'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo" style="margin-bottom:0;">
            <label for="q">Código, teléfono o nombre</label>
            <input type="search" id="q" name="q" maxlength="40" value="<?= htmlspecialchars($filtros['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 7FKDXB">
        </div>
        <button type="submit" class="btn">Filtrar</button>
        <a class="btn btn-secundario" href="/admin/pedidos">Limpiar</a>
    </form>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($filas === []): ?>
        <p class="tabla-vacio">No hay pedidos con esos filtros.</p>
    <?php else: ?>
        <p class="campo-ayuda" style="margin-top:0;"><?= (int) $total ?> pedido(s).</p>
        <table class="tabla">
            <thead><tr><th>Código</th><th>Fecha y hora</th><th>Cliente</th><th>Pago</th><th>Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr class="<?= $f['estado'] === 'enviado' ? 'fila-pedido-nuevo' : '' ?>">
                        <td><strong>#<?= htmlspecialchars($f['codigo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars(substr((string) $f['creado_en'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars((string) ($f['cliente_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($f['cliente_telefono'])): ?>
                                <br><small><?= htmlspecialchars($f['cliente_telefono'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(Pedido::ETIQUETAS_PAGO[$f['forma_pago']] ?? $f['forma_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(Dinero::formato((int) $f['total_centavos']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="insignia insignia-pedido-<?= htmlspecialchars($f['estado'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(Pedido::ETIQUETAS_ESTADO[$f['estado']] ?? $f['estado'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><a class="btn btn-pequeno" href="/admin/pedidos/<?= (int) $f['id'] ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPaginas > 1): ?>
            <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                    <a class="btn <?= $p === $pagina ? '' : 'btn-secundario' ?> btn-pequeno" href="<?= htmlspecialchars($consulta(['pagina' => $p === 1 ? '' : (string) $p]), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
