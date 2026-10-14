<?php
/**
 * @var array<string,mixed> $pedido
 * @var array<int,array<string,mixed>> $items
 * @var array<int,array<string,mixed>> $historial
 * @var array<string,array{motivo:bool,solo_admin:bool}> $acciones
 */
use App\Core\Csrf;
use App\Core\Dinero;
use App\Models\Pedido;

require APP_PATH . '/Views/layouts/panel_header.php';

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$estado = (string) $pedido['estado'];
$textosBoton = ['confirmado' => 'Confirmar pedido', 'entregado' => 'Marcar como entregado', 'cancelado' => 'Cancelar pedido', 'anulado' => 'Anular pedido'];
?>

<div class="panel-encabezado">
    <h1>Pedido #<?= $e($pedido['codigo']) ?></h1>
    <a class="btn btn-secundario" href="/admin/pedidos">← Volver a Pedidos</a>
</div>

<div class="tarjeta" style="margin-bottom:16px;">
    <p style="margin-top:0;">
        <span class="insignia insignia-pedido-<?= $e($estado) ?>"><?= $e(Pedido::ETIQUETAS_ESTADO[$estado] ?? $estado) ?></span>
        &nbsp;Creado: <strong><?= $e(substr((string) $pedido['creado_en'], 0, 16)) ?></strong>
        <?php if (!empty($pedido['entregado_en'])): ?>
            · Entregado: <strong><?= $e(substr((string) $pedido['entregado_en'], 0, 16)) ?></strong>
        <?php endif; ?>
    </p>
    <dl class="datos-pedido">
        <dt>Cliente</dt><dd><?= $e($pedido['cliente_nombre'] ?? '—') ?></dd>
        <?php if (!empty($pedido['cliente_telefono'])): ?><dt>Teléfono</dt><dd><?= $e($pedido['cliente_telefono']) ?></dd><?php endif; ?>
        <dt>Dirección</dt><dd><?= $e($pedido['direccion'] ?? '—') ?></dd>
        <dt>Forma de pago</dt><dd><?= $e(Pedido::ETIQUETAS_PAGO[$pedido['forma_pago']] ?? $pedido['forma_pago']) ?></dd>
        <?php if (!empty($pedido['nota'])): ?><dt>Detalles del cliente</dt><dd><?= $e($pedido['nota']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($estado === 'entregado' && !empty($pedido['cliente_id'])): ?>
        <p style="margin-bottom:0;">
            Este pedido es de un cliente con cuenta de lealtad
            (<?= $e($pedido['cliente_cuenta_nombre'] ?? '') ?>). Los cafés <strong>no se suman solos</strong>:
            <a href="/admin/lealtad/<?= (int) $pedido['cliente_id'] ?>">registrar consumo de ese cliente</a>.
        </p>
    <?php endif; ?>
</div>

<div class="tarjeta tabla-contenedor" style="margin-bottom:16px;">
    <h2 style="margin-top:0;">Productos</h2>
    <table class="tabla">
        <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= $e($it['nombre_snapshot']) ?><?php if ((int) $it['es_cortesia'] === 1): ?> <em>(cortesía)</em><?php endif; ?></td>
                    <td><?= (int) $it['cantidad'] ?></td>
                    <td><?= $e(Dinero::formato((int) $it['precio_unitario_centavos'])) ?></td>
                    <td><?= $e(Dinero::formato((int) $it['subtotal_centavos'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ((int) $pedido['descuento_centavos'] > 0): ?>
                <tr><td colspan="3" style="text-align:right;">Subtotal</td><td><?= $e(Dinero::formato((int) $pedido['subtotal_centavos'])) ?></td></tr>
                <tr><td colspan="3" style="text-align:right;">Descuento</td><td>−<?= $e(Dinero::formato((int) $pedido['descuento_centavos'])) ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="3" style="text-align:right;"><strong>Total</strong></td><td><strong><?= $e(Dinero::formato((int) $pedido['total_centavos'])) ?></strong></td></tr>
        </tfoot>
    </table>
    <p class="campo-ayuda" style="margin-bottom:0;">Precios y nombres tal como estaban al momento del pedido: no cambian aunque el menú cambie.</p>
</div>

<?php if ($acciones !== []): ?>
    <div class="tarjeta" style="margin-bottom:16px;">
        <h2 style="margin-top:0;">Cambiar estado</h2>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;">
            <?php foreach ($acciones as $nuevo => $regla): ?>
                <form method="post" action="/admin/pedidos/<?= (int) $pedido['id'] ?>/estado" style="flex:1;min-width:240px;"
                      onsubmit="this.querySelector('button').disabled = true;">
                    <?= Csrf::campo() ?>
                    <input type="hidden" name="estado_actual" value="<?= $e($estado) ?>">
                    <input type="hidden" name="nuevo_estado" value="<?= $e($nuevo) ?>">
                    <?php if ($regla['motivo']): ?>
                        <div class="campo">
                            <label for="motivo_<?= $e($nuevo) ?>">Motivo (obligatorio)</label>
                            <input type="text" id="motivo_<?= $e($nuevo) ?>" name="motivo" maxlength="255" required
                                   placeholder="<?= $nuevo === 'anulado' ? 'Ej. se entregó por error / devolución' : 'Ej. el cliente ya no lo quiere' ?>">
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn <?= in_array($nuevo, ['cancelado', 'anulado'], true) ? 'btn-peligro' : 'btn-verde' ?>"
                        <?php if (in_array($nuevo, ['cancelado', 'anulado', 'entregado'], true)): ?>
                            onclick="return confirm('<?= $e($textosBoton[$nuevo]) ?>?<?= $nuevo === 'anulado' ? ' El pedido dejará de contar como venta.' : '' ?>');"
                        <?php endif; ?>><?= $e($textosBoton[$nuevo]) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="tarjeta tabla-contenedor">
    <h2 style="margin-top:0;">Historial</h2>
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Cambio</th><th>Quién</th><th>Motivo</th></tr></thead>
        <tbody>
            <?php foreach ($historial as $h): ?>
                <tr>
                    <td><?= $e(substr((string) $h['fecha'], 0, 19)) ?></td>
                    <td>
                        <?= $h['estado_anterior'] !== null ? $e(Pedido::ETIQUETAS_ESTADO[$h['estado_anterior']] ?? $h['estado_anterior']) . ' → ' : 'Creado como ' ?>
                        <strong><?= $e(Pedido::ETIQUETAS_ESTADO[$h['estado_nuevo']] ?? $h['estado_nuevo']) ?></strong>
                    </td>
                    <td><?= $h['usuario_id'] === null && ($h['estado_anterior'] === null || ($h['estado_anterior'] === 'pendiente' && $h['estado_nuevo'] === 'enviado')) ? 'El cliente' : $e($h['usuario_nombre'] ?? 'Usuario eliminado') ?></td>
                    <td><?= $e($h['motivo'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
