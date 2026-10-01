<?php
/**
 * @var array<int,array<string,mixed>> $registros
 * @var int $pagina
 * @var int $totalPaginas
 * @var array{tabla:string,accion:string} $filtros
 * @var array<int,string> $tablasConocidas
 * @var array<int,string> $accionesConocidas
 */
require APP_PATH . '/Views/layouts/panel_header.php';

$etiquetasAccion = [
    'crear' => 'Creación', 'editar' => 'Edición', 'eliminar' => 'Eliminación',
    'login' => 'Inicio de sesión', 'consumo' => 'Registrar consumo', 'ajuste' => 'Ajuste manual', 'canje' => 'Canje de recompensa',
];
?>

<h1>Auditoría</h1>

<div class="tarjeta" style="margin-bottom:16px;">
    <form method="get" action="/admin/auditoria" class="form-filtros" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="campo" style="margin-bottom:0;">
            <label for="tabla">Módulo</label>
            <select id="tabla" name="tabla">
                <option value="">Todos</option>
                <?php foreach ($tablasConocidas as $t): ?>
                    <option value="<?= $t ?>" <?= $filtros['tabla'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo" style="margin-bottom:0;">
            <label for="accion">Acción</label>
            <select id="accion" name="accion">
                <option value="">Todas</option>
                <?php foreach ($accionesConocidas as $a): ?>
                    <option value="<?= $a ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= $etiquetasAccion[$a] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Filtrar</button>
    </form>
</div>

<div class="tarjeta tabla-contenedor">
    <?php if ($registros === []): ?>
        <p class="tabla-vacio">No hay registros de auditoría con esos filtros.</p>
    <?php else: ?>
        <table class="tabla tabla-primera-sin-corte">
            <thead><tr><th>Fecha</th><th>Módulo</th><th>Acción</th><th>Registro</th><th>Cliente</th><th>Detalle</th><th>Usuario</th><th>IP</th></tr></thead>
            <tbody>
                <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['creado_en'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['tabla_afectada'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($etiquetasAccion[$r['accion']] ?? $r['accion'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>#<?= (int) $r['registro_id'] ?></td>
                        <td><?= htmlspecialchars((string) ($r['cliente_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <?php
                        $d = $r['tabla_afectada'] === 'lealtad_movimientos' && $r['valores_despues'] !== null
                            ? (json_decode((string) $r['valores_despues'], true) ?: []) : [];
                        $partes = [];
                        if (isset($d['tipo'])) {
                            $partes[] = $d['tipo'] === 'ajuste_negativo' ? 'Restar' : 'Sumar';
                        }
                        if (isset($d['cantidad'])) {
                            $partes[] = (int) $d['cantidad'] . ' café(s)';
                        }
                        if (!empty($d['motivo'])) {
                            $partes[] = 'Motivo: ' . $d['motivo'];
                        }
                        ?>
                        <td><?= htmlspecialchars($partes !== [] ? implode(' · ', $partes) : '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['usuario_nombre'] ?? 'Sistema'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['ip'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPaginas > 1): ?>
            <div style="margin-top:16px;display:flex;gap:8px;">
                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                    <a class="btn <?= $p === $pagina ? '' : 'btn-secundario' ?> btn-pequeno"
                       href="/admin/auditoria?pagina=<?= $p ?>&tabla=<?= urlencode($filtros['tabla']) ?>&accion=<?= urlencode($filtros['accion']) ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
