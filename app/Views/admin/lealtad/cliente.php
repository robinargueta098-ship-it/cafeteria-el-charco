<?php
/**
 * @var array<string,mixed> $cliente
 * @var int $umbral
 * @var array<int,array<string,mixed>> $historial
 * @var array<int,array<string,mixed>> $productos
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$saldo = (int) $cliente['cafes_acumulados'];
$puedeCanjear = $saldo >= $umbral;
$porcentaje = min(100, (int) round(($saldo / max(1, $umbral)) * 100));
?>

<h1><?= htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8') ?></p>

<div class="tarjeta" style="margin-bottom:16px;">
    <h2 style="margin-top:0;">Progreso de lealtad</h2>
    <p><?= $saldo ?> de <?= $umbral ?> cafés (el sobrante después de cada canje se conserva para el siguiente ciclo).</p>
    <div class="progreso-lealtad">
        <div class="progreso-lealtad-barra" style="width:<?= $porcentaje ?>%;"></div>
    </div>
    <?php if ($puedeCanjear): ?>
        <p><strong>✔ Tiene una recompensa disponible.</strong></p>
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
        <h2 style="margin-top:0;">Canjear recompensa</h2>
        <?php if (!$puedeCanjear): ?>
            <p class="campo-ayuda">El cliente todavía no acumula los <?= $umbral ?> cafés necesarios.</p>
        <?php else: ?>
            <form method="post" action="/admin/lealtad/<?= (int) $cliente['id'] ?>/canjear">
                <?= Csrf::campo() ?>
                <div class="campo">
                    <label for="producto_id">Café gratis a entregar</label>
                    <select id="producto_id" name="producto_id" required>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-verde"
                        onclick="return confirm('¿Confirmar el canje? Se restarán <?= $umbral ?> cafés del saldo.');">
                    Canjear
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<h2>Historial</h2>
<div class="tarjeta tabla-contenedor">
    <?php if ($historial === []): ?>
        <p class="tabla-vacio">Todavía no hay movimientos para este cliente.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Detalle</th><th>Registrado por</th></tr></thead>
            <tbody>
                <?php foreach ($historial as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= ['compra' => 'Consumo', 'canje' => 'Canje', 'ajuste' => 'Ajuste'][$m['tipo']] ?? $m['tipo'] ?></td>
                        <td><?= (int) $m['cantidad'] ?></td>
                        <td><?= $m['producto_nombre'] !== null ? htmlspecialchars($m['producto_nombre'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                        <td><?= htmlspecialchars($m['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
