<?php
/**
 * @var array<string,mixed> $cliente
 * @var int $umbral
 * @var array<int,array<string,mixed>> $historial
 * @var array<int,array<string,mixed>> $recompensas
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$saldo = (int) $cliente['cafes_acumulados'];
$porcentaje = min(100, (int) round(($saldo / max(1, $umbral)) * 100));
$disponibles = array_filter($recompensas, fn ($r) => $r['estado'] === 'disponible');

$etiquetasTipo = [
    'consumo' => 'Consumo', 'ajuste_positivo' => 'Ajuste (+)', 'ajuste_negativo' => 'Ajuste (-)',
    'recompensa_generada' => 'Recompensa generada', 'recompensa_canjeada' => 'Recompensa canjeada',
    'recompensa_cancelada' => 'Recompensa cancelada',
];
$etiquetasEstadoRecompensa = ['disponible' => 'Disponible', 'canjeada' => 'Canjeada', 'cancelada' => 'Cancelada', 'vencida' => 'Vencida'];
?>

<h1><?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="tarjeta" style="margin-bottom:16px;">
    <h2 style="margin-top:0;">Progreso hacia la próxima recompensa</h2>
    <p><?= $saldo ?> de <?= $umbral ?> cafés acumulados (el umbral se lee de Configuración; cambiarlo ahí cambia esta regla para todos).</p>
    <div class="progreso-lealtad">
        <div class="progreso-lealtad-barra" style="width:<?= $porcentaje ?>%;"></div>
    </div>
    <?php if ($disponibles !== []): ?>
        <p>
            <strong>✔ Tiene <?= count($disponibles) ?> recompensa(s) disponible(s).</strong>
            <a class="btn btn-verde btn-pequeno" href="/admin/lealtad/canjear?telefono=<?= urlencode((string) $cliente['telefono']) ?>">Canjear</a>
        </p>
    <?php endif; ?>
</div>

<div style="display:flex;gap:16px;flex-wrap:wrap;">
    <div class="tarjeta" style="flex:1;min-width:280px;">
        <h2 style="margin-top:0;">Registrar consumo</h2>
        <form method="post" action="/admin/lealtad/<?= (int) $cliente['id'] ?>/consumo">
            <?= Csrf::campo() ?>
            <div class="campo">
                <label for="cantidad">Cantidad de cafés comprados</label>
                <input type="number" id="cantidad" name="cantidad" min="1" value="1" required>
            </div>
            <button type="submit" class="btn">Registrar</button>
        </form>
    </div>

    <div class="tarjeta" style="flex:1;min-width:280px;">
        <h2 style="margin-top:0;">Ajuste manual</h2>
        <form method="post" action="/admin/lealtad/<?= (int) $cliente['id'] ?>/ajuste">
            <?= Csrf::campo() ?>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo" required>
                    <option value="ajuste_positivo">Sumar cafés</option>
                    <option value="ajuste_negativo">Restar cafés</option>
                </select>
            </div>
            <div class="campo">
                <label for="ajuste_cantidad">Cantidad</label>
                <input type="number" id="ajuste_cantidad" name="cantidad" min="1" required>
            </div>
            <div class="campo">
                <label for="motivo">Motivo (obligatorio)</label>
                <input type="text" id="motivo" name="motivo" maxlength="255" required
                       placeholder="Ej. corrección de un registro anterior">
            </div>
            <button type="submit" class="btn btn-secundario">Aplicar ajuste</button>
        </form>
    </div>
</div>

<h2>Recompensas</h2>
<div class="tarjeta tabla-contenedor">
    <?php if ($recompensas === []): ?>
        <p class="tabla-vacio">Todavía no ha generado ninguna recompensa.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Código</th><th>Estado</th><th>Generada</th><th>Vence</th><th>Café entregado</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($recompensas as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="insignia insignia-<?= $r['estado'] === 'disponible' ? 'activo' : 'inactivo' ?>"><?= $etiquetasEstadoRecompensa[$r['estado']] ?></span></td>
                        <td><?= htmlspecialchars(substr($r['fecha_generada'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $r['fecha_vencimiento'] !== null ? htmlspecialchars(substr($r['fecha_vencimiento'], 0, 10), ENT_QUOTES, 'UTF-8') : 'No vence' ?></td>
                        <td><?= htmlspecialchars((string) ($r['producto_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($r['estado'] === 'disponible'): ?>
                                <form method="post" action="/admin/recompensas/<?= (int) $r['id'] ?>/cancelar"
                                      onsubmit="return confirm('¿Cancelar esta recompensa? Los cafés se devolverán al saldo.');">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="motivo" value="Cancelada manualmente desde la ficha del cliente">
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<h2>Historial de movimientos</h2>
<div class="tarjeta tabla-contenedor">
    <?php if ($historial === []): ?>
        <p class="tabla-vacio">Todavía no hay movimientos para este cliente.</p>
    <?php else: ?>
        <table class="tabla tabla-primera-sin-corte">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Motivo</th><th>Referencia</th><th>Registrado por</th></tr></thead>
            <tbody>
                <?php foreach ($historial as $m): ?>
                    <tr style="<?= $m['estado'] === 'anulado' ? 'opacity:0.5;text-decoration:line-through;' : '' ?>">
                        <td><?= htmlspecialchars($m['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $etiquetasTipo[$m['tipo']] ?? $m['tipo'] ?></td>
                        <td><?= (int) $m['cantidad'] ?></td>
                        <td><?= htmlspecialchars((string) ($m['motivo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($m['referencia'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($m['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
