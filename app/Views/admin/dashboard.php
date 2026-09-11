<?php
/**
 * @var array{id:int,nombre:string,rol_id:int} $usuario
 * @var bool $esAdmin
 * @var int $clientesTotal
 * @var int $recompensasDisponibles
 */
require APP_PATH . '/Views/layouts/panel_header.php';
?>

<h1>Bienvenido, <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
    <div class="tarjeta" style="flex:1;min-width:180px;">
        <div style="font-size:2rem;font-weight:700;"><?= $clientesTotal ?></div>
        <div>Clientes registrados</div>
    </div>
    <div class="tarjeta" style="flex:1;min-width:180px;">
        <div style="font-size:2rem;font-weight:700;"><?= $recompensasDisponibles ?></div>
        <div>Con recompensa disponible</div>
    </div>
    <?php if ($esAdmin): ?>
        <div class="tarjeta" style="flex:1;min-width:180px;">
            <div style="font-size:2rem;font-weight:700;"><?= $productosActivos ?></div>
            <div>Productos activos</div>
        </div>
        <div class="tarjeta" style="flex:1;min-width:180px;">
            <div style="font-size:2rem;font-weight:700;"><?= $promocionesVigentes ?></div>
            <div>Promociones vigentes hoy</div>
        </div>
    <?php endif; ?>
</div>

<?php if ($esAdmin): ?>
    <h2>Actividad reciente</h2>
    <div class="tarjeta tabla-contenedor">
        <?php if ($ultimaActividad === []): ?>
            <p class="tabla-vacio">Todavía no hay actividad registrada.</p>
        <?php else: ?>
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Módulo</th><th>Acción</th><th>Usuario</th></tr></thead>
                <tbody>
                    <?php foreach ($ultimaActividad as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['tabla_afectada'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['accion'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($r['usuario_nombre'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><a href="/admin/auditoria">Ver toda la auditoría →</a></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
