<?php
/**
 * Vista genérica de un reporte (arma tarjetas, gráfico y tablas que le entrega ReporteController).
 *
 * @var string $seccion
 * @var array<string,array{0:string,1:bool}> $secciones
 * @var bool $esAdmin
 * @var array<string,mixed> $periodo
 * @var array<string,mixed> $reporte
 * @var array<string,string> $consulta
 * @var string $generado
 * @var string $usuarioNombre
 * @var string $nombreSitio
 */
use App\Core\Csrf;
use App\Core\Dinero;
use App\Controllers\Admin\ReporteController;

require APP_PATH . '/Views/layouts/panel_header.php';

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$usaPeriodo = !isset($reporte['usaPeriodo']) || $reporte['usaPeriodo'] !== false;

/** Celda según su tipo de columna. */
$celda = function ($valor, string $tipo) use ($e): string {
    if ($valor === null || $valor === '') {
        return '';
    }
    switch ($tipo) {
        case 'dinero':
            return $e(Dinero::formato((int) $valor));
        case 'entero':
            return $e(number_format((int) $valor, 0, '.', ','));
        case 'porcentaje':
            return $e(Dinero::decimal((int) $valor) . ' %');
        default:
            return $e($valor);
    }
};

/** Enlace conservando los filtros actuales. */
$enlace = function (array $extra) use ($seccion, $consulta, $e): string {
    $q = array_merge($consulta, $extra);
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== null; });
    $base = $seccion === 'resumen' ? '/admin/reportes' : '/admin/reportes/' . $seccion;

    return $e($base . ($q !== [] ? '?' . http_build_query($q) : ''));
};
?>

<div class="panel-encabezado no-imprimir">
    <h1>Reportes</h1>
</div>

<nav class="reportes-pestanas no-imprimir" aria-label="Tipo de reporte">
    <?php foreach ($secciones as $clave => $datos): ?>
        <?php if ($datos[1] && !$esAdmin) { continue; } ?>
        <?php if ($clave === 'consumos' && $esAdmin) { continue; } ?>
        <a href="<?= $clave === 'resumen' ? '/admin/reportes' : '/admin/reportes/' . $e($clave) ?>"
           class="<?= $clave === $seccion ? 'activo' : '' ?>"><?= $e($datos[0]) ?></a>
    <?php endforeach; ?>
</nav>

<?php /* Solo se ve al imprimir / guardar como PDF */ ?>
<div class="solo-impresion">
    <h1><?= $e($nombreSitio) ?></h1>
    <p><strong>Reporte: <?= $e($reporte['titulo']) ?></strong><?php if ($usaPeriodo): ?> — <?= $e($periodo['etiqueta']) ?><?php endif; ?></p>
    <p>Generado el <?= $e($generado) ?> por <?= $e($usuarioNombre) ?></p>
</div>

<h2 style="margin-top:0;"><?= $e($reporte['titulo']) ?></h2>

<?php if ($usaPeriodo || !empty($reporte['filtros'])): ?>
    <div class="tarjeta no-imprimir" style="margin-bottom:16px;">
        <form method="get" action="<?= $seccion === 'resumen' ? '/admin/reportes' : '/admin/reportes/' . $e($seccion) ?>" class="form-filtros" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;" id="form-reporte">
            <?php if ($usaPeriodo): ?>
                <div class="campo" style="margin-bottom:0;">
                    <label for="periodo">Periodo</label>
                    <select id="periodo" name="periodo">
                        <?php foreach (\App\Core\ReportePeriodo::OPCIONES as $clave => $etiqueta): ?>
                            <option value="<?= $e($clave) ?>" <?= $periodo['clave'] === $clave ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo campo-rango" style="margin-bottom:0;" <?= $periodo['clave'] === 'rango' ? '' : 'hidden' ?>>
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= $e($periodo['clave'] === 'rango' ? $periodo['desde'] : '') ?>" max="<?= $e(date('Y-m-d')) ?>">
                </div>
                <div class="campo campo-rango" style="margin-bottom:0;" <?= $periodo['clave'] === 'rango' ? '' : 'hidden' ?>>
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= $e($periodo['clave'] === 'rango' ? $periodo['hasta'] : '') ?>" max="<?= $e(date('Y-m-d')) ?>">
                </div>
            <?php endif; ?>
            <?php foreach (isset($reporte['filtros']) ? $reporte['filtros'] : [] as $f): ?>
                <div class="campo" style="margin-bottom:0;">
                    <label for="f_<?= $e($f['nombre']) ?>"><?= $e($f['etiqueta']) ?></label>
                    <?php if ($f['tipo'] === 'select'): ?>
                        <select id="f_<?= $e($f['nombre']) ?>" name="<?= $e($f['nombre']) ?>">
                            <?php foreach ($f['opciones'] as $valor => $etiqueta): ?>
                                <option value="<?= $e($valor) ?>" <?= (string) $f['valor'] === (string) $valor ? 'selected' : '' ?>><?= $e($etiqueta) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="search" id="f_<?= $e($f['nombre']) ?>" name="<?= $e($f['nombre']) ?>" maxlength="40" value="<?= $e($f['valor']) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn">Ver reporte</button>
        </form>
    </div>
<?php endif; ?>

<?php foreach ($periodo['errores'] as $aviso): ?>
    <div class="alerta alerta-error"><?= $e($aviso) ?></div>
<?php endforeach; ?>

<?php if (!empty($reporte['mensaje'])): ?>
    <div class="alerta <?= $reporte['mensaje']['tipo'] === 'exito' ? 'alerta-exito' : 'alerta-error' ?>"><strong><?= $e($reporte['mensaje']['texto']) ?></strong></div>
<?php endif; ?>

<p class="campo-ayuda" style="margin-top:0;">
    <?php if ($usaPeriodo): ?><strong><?= $e($periodo['etiqueta']) ?></strong>. <?php endif; ?><?= $e($reporte['ayuda']) ?>
</p>

<div class="no-imprimir" style="margin-bottom:16px;">
    <button type="button" class="btn btn-secundario" id="boton-imprimir">Imprimir / Guardar PDF</button>
</div>

<?php if (!empty($reporte['tarjetas'])): ?>
    <div class="reportes-tarjetas">
        <?php foreach ($reporte['tarjetas'] as $t): ?>
            <div class="reporte-tarjeta">
                <span class="reporte-tarjeta-etiqueta"><?= $e($t['etiqueta']) ?></span>
                <strong class="reporte-tarjeta-valor"><?= $e($t['valor']) ?></strong>
                <?php if ($t['nota'] !== ''): ?><span class="reporte-tarjeta-nota"><?= $e($t['nota']) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
// ---- Gráfico de barras SVG (sin librerías) ----
if (!empty($reporte['grafico']) && $reporte['grafico']['puntos'] !== []):
    $puntos = $reporte['grafico']['puntos'];
    $n = count($puntos);
    $max = 0;
    foreach ($puntos as $p) {
        $max = max($max, (int) $p['valor']);
    }
    $W = 760; $H = 260; $mi = 78; $md = 12; $ms = 16; $mb = 42;
    $ancho = $W - $mi - $md;
    $alto = $H - $ms - $mb;
    $paso = $ancho / $n;
    $barra = max(1.5, $paso * 0.7);
    $cadaX = max(1, (int) ceil($n / 8));
    ?>
    <div class="tarjeta reporte-grafico">
        <h3 style="margin-top:0;"><?= $e($reporte['grafico']['titulo']) ?></h3>
        <?php if ($max === 0): ?>
            <p class="tabla-vacio">No hay ventas en este periodo.</p>
        <?php else: ?>
            <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-labelledby="graf-titulo graf-desc" class="grafico-svg">
                <title id="graf-titulo"><?= $e($reporte['grafico']['titulo']) ?></title>
                <desc id="graf-desc">Barras con las ventas de cada día; el valor más alto es <?= $e(Dinero::formato($max)) ?>.</desc>
                <?php foreach ([0, 0.5, 1] as $fraccion): ?>
                    <?php $y = $ms + $alto - $alto * $fraccion; ?>
                    <line x1="<?= $mi ?>" y1="<?= round($y, 1) ?>" x2="<?= $W - $md ?>" y2="<?= round($y, 1) ?>" class="grafico-linea"/>
                    <text x="<?= $mi - 8 ?>" y="<?= round($y + 4, 1) ?>" text-anchor="end" class="grafico-texto"><?= $e(Dinero::formato((int) round($max * $fraccion))) ?></text>
                <?php endforeach; ?>
                <?php foreach ($puntos as $i => $p): ?>
                    <?php
                    $h = $max > 0 ? $alto * ((int) $p['valor'] / $max) : 0;
                    $x = $mi + $paso * $i + ($paso - $barra) / 2;
                    ?>
                    <rect x="<?= round($x, 1) ?>" y="<?= round($ms + $alto - $h, 1) ?>" width="<?= round($barra, 1) ?>" height="<?= round($h, 1) ?>" class="grafico-barra">
                        <title><?= $e($p['etiqueta'] . ': ' . Dinero::formato((int) $p['valor'])) ?></title>
                    </rect>
                    <?php if ($i % $cadaX === 0): ?>
                        <text x="<?= round($mi + $paso * $i + $paso / 2, 1) ?>" y="<?= $H - 18 ?>" text-anchor="middle" class="grafico-texto"><?= $e(substr($p['etiqueta'], 8, 2) . '/' . substr($p['etiqueta'], 5, 2)) ?></text>
                    <?php endif; ?>
                <?php endforeach; ?>
            </svg>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($reporte['tablas'] as $id => $tabla): ?>
    <div class="tarjeta tabla-contenedor" style="margin-bottom:16px;">
        <div class="reporte-tabla-cabecera">
            <h3 style="margin:0;"><?= $e($tabla['titulo']) ?></h3>
            <?php if ($tabla['filas'] !== [] || !empty($tabla['total_filas'])): ?>
                <a class="btn btn-secundario btn-pequeno no-imprimir" href="<?= $enlace(['formato' => 'xlsx', 'tabla' => $id, 'pagina' => '']) ?>">Exportar a Excel</a>
            <?php endif; ?>
        </div>
        <?php if (!empty($tabla['nota'])): ?><p class="campo-ayuda"><?= $e($tabla['nota']) ?></p><?php endif; ?>

        <?php if ($tabla['filas'] === []): ?>
            <p class="tabla-vacio">No hay datos para este periodo o filtro.</p>
        <?php else: ?>
            <table class="tabla tabla-reporte">
                <thead>
                    <tr>
                        <?php foreach ($tabla['columnas'] as $c): ?>
                            <th class="<?= in_array($c['tipo'], ['dinero', 'entero', 'porcentaje'], true) ? 'num' : '' ?>"><?= $e($c['etiqueta']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tabla['filas'] as $fila): ?>
                        <tr>
                            <?php foreach ($tabla['columnas'] as $c): ?>
                                <td class="<?= in_array($c['tipo'], ['dinero', 'entero', 'porcentaje'], true) ? 'num' : '' ?>"><?= $celda(isset($fila[$c['clave']]) ? $fila[$c['clave']] : null, $c['tipo']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($tabla['pie'])): ?>
                    <tfoot>
                        <tr>
                            <?php foreach ($tabla['columnas'] as $c): ?>
                                <td class="<?= in_array($c['tipo'], ['dinero', 'entero', 'porcentaje'], true) ? 'num' : '' ?>"><strong><?= $celda(isset($tabla['pie'][$c['clave']]) ? $tabla['pie'][$c['clave']] : null, $c['tipo']) ?></strong></td>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>

            <?php if (!empty($tabla['paginas']) && $tabla['paginas'] > 1): ?>
                <p class="campo-ayuda no-imprimir"><?= (int) $tabla['total_filas'] ?> filas; la exportación incluye todas.</p>
                <div class="no-imprimir" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                    <?php for ($p = 1; $p <= $tabla['paginas']; $p++): ?>
                        <a class="btn <?= $p === $tabla['pagina'] ? '' : 'btn-secundario' ?> btn-pequeno" href="<?= $enlace(['pagina' => $p === 1 ? '' : (string) $p]) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<script>
(function () {
  var periodo = document.getElementById('periodo');
  if (periodo) {
    periodo.addEventListener('change', function () {
      var rango = periodo.value === 'rango';
      Array.prototype.forEach.call(document.querySelectorAll('.campo-rango'), function (c) { c.hidden = !rango; });
      if (!rango) { document.getElementById('form-reporte').submit(); }   // los periodos fijos se aplican al elegirlos
    });
  }

  var boton = document.getElementById('boton-imprimir');
  if (boton) {
    boton.addEventListener('click', function () {
      // Queda en Auditoría que se imprimió este reporte; luego se abre el diálogo de impresión.
      var datos = new URLSearchParams(window.location.search);
      var cuerpo = new URLSearchParams();
      cuerpo.set('csrf_token', <?= json_encode(Csrf::token()) ?>);
      cuerpo.set('seccion', <?= json_encode($seccion) ?>);
      ['periodo', 'desde', 'hasta'].forEach(function (k) { if (datos.get(k)) { cuerpo.set(k, datos.get(k)); } });
      fetch('/admin/reportes/imprimir', { method: 'POST', body: cuerpo, credentials: 'same-origin' })
        .then(function () { window.print(); }, function () { window.print(); });
    });
  }
})();
</script>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
