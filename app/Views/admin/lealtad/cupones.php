<?php
/**
 * @var array<int,array<string,mixed>> $cupones
 */
use App\Core\Auth;
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$etiquetasEstado = ['disponible' => 'Disponible', 'canjeado' => 'Canjeado', 'cancelado' => 'Cancelado', 'vencido' => 'Vencido'];
// Cancelar un cupón es exclusivo de Administrador (docs/arquitectura.md §4.20,
// punto 11) — el Cajero ve el mismo historial pero sin el botón de cancelar.
$esAdministrador = (Auth::usuario()['rol_id'] ?? null) === 1;
?>

<div class="panel-encabezado">
    <h1>Cupones de promoción</h1>
    <a href="/admin/lealtad/canjear" class="btn">Escanear código</a>
</div>
<p class="campo-ayuda" style="margin-top:-8px;">
    Cupones reclamados por clientes desde "Mi cuenta" para una promoción marcada "combinable con lealtad".
    No están atados a cafés acumulados — eso son las <a href="/admin/recompensas">Recompensas</a>.
</p>

<div class="tarjeta tabla-contenedor">
    <?php if ($cupones === []): ?>
        <p class="tabla-vacio">Todavía no se ha reclamado ningún cupón.</p>
    <?php else: ?>
        <table class="tabla tabla-primera-sin-corte">
            <thead><tr><th>Código</th><th>Cliente</th><th>Promoción</th><th>Estado</th><th>Reclamado</th><th>Canjeado por</th><th>Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($cupones as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['codigo'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><a href="/admin/lealtad/<?= (int) $c['cliente_id'] ?>"><?= htmlspecialchars($c['cliente_nombre'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars($c['promocion_nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="insignia insignia-<?= $c['estado'] === 'disponible' ? 'activo' : 'inactivo' ?>">
                                <?= $etiquetasEstado[$c['estado']] ?? $c['estado'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(substr((string) $c['fecha_reclamo'], 0, 16), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($c['usuario_canjeo_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="acciones-tabla">
                            <?php if ($c['estado'] === 'disponible' && $esAdministrador): ?>
                                <form method="post" action="/admin/cupones/<?= (int) $c['id'] ?>/cancelar"
                                      onsubmit="return confirm('¿Cancelar este cupón? No se puede deshacer.');">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="motivo" value="Cancelado manualmente desde Cupones">
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

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
