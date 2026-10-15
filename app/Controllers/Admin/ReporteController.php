<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Xlsx;
use App\Core\Dinero;
use App\Core\Entrada;
use App\Core\ReportePeriodo;
use App\Models\Configuracion;
use App\Models\Pedido;
use App\Services\AuditoriaService;
use App\Services\LealtadService;
use App\Services\ReporteLealtadService;
use App\Services\VentasService;
use App\Services\VerificacionVentas;
use App\Core\AuditoriaTexto;
use App\Core\Database;

/**
 * Módulo «Reportes». Administrador: todo. Cajero: solo Resumen, Pedidos y sus propios Consumos (con los teléfonos
 * enmascarados). Cada reporte arma una estructura de secciones (tarjetas, gráfico, tablas) que dibuja la vista
 * `admin/reportes/reporte` y que también alimenta la exportación a Excel, así pantalla y Excel nunca difieren.
 *
 * Las VENTAS salen siempre de VentasService (una sola definición). Compatible con PHP 7.4.
 */
class ReporteController extends Controller
{
    private const POR_PAGINA = 50;

    /** sección => [etiqueta, solo_admin] */
    public const SECCIONES = [
        'resumen'      => ['Resumen', false],
        'ventas'       => ['Ventas', true],
        'pedidos'      => ['Pedidos', false],
        'lealtad'      => ['Lealtad', true],
        'clientes'     => ['Clientes', true],
        'consumos'     => ['Mis consumos', false],
        'ajustes'      => ['Ajustes y auditoría', true],
        'catalogo'     => ['Promociones y menú', true],
        'verificacion' => ['Verificación de datos', true],
    ];

    public function mostrar(string $seccion = 'resumen'): void
    {
        $this->requerirPermiso('reportes', 'ver');

        $usuario = Auth::usuario();
        $esAdmin = (int) $usuario['rol_id'] === 1;

        if (!isset(self::SECCIONES[$seccion])) {
            $this->error404();
        }
        if (self::SECCIONES[$seccion][1] && !$esAdmin) {
            http_response_code(403);
            require APP_PATH . '/Views/errores/403.php';
            exit;
        }
        // «Mis consumos» es para quien registra consumos; el Administrador ve el reporte completo en «Lealtad».
        if ($seccion === 'consumos' && $esAdmin) {
            $this->redirigir('/admin/reportes/lealtad');
        }

        $periodo = ReportePeriodo::resolver($_GET);
        $reporte = $this->construir($seccion, $periodo, $esAdmin, (int) $usuario['id']);

        // --- Exportación a Excel: TODO el resultado filtrado, no solo la página ---
        if (Entrada::texto($_GET, 'formato', '') === 'xlsx') {
            $this->exportar($seccion, $reporte, $periodo);
        }

        // --- Paginación en pantalla (50 filas) ---
        $pagina = max(1, (int) Entrada::texto($_GET, 'pagina', '1'));
        foreach ($reporte['tablas'] as $id => &$tabla) {
            $tabla['total_filas'] = count($tabla['filas']);
            if (!empty($tabla['paginar']) && $tabla['total_filas'] > self::POR_PAGINA) {
                $tabla['paginas'] = (int) ceil($tabla['total_filas'] / self::POR_PAGINA);
                $tabla['pagina'] = min($pagina, $tabla['paginas']);
                $tabla['filas'] = array_slice($tabla['filas'], ($tabla['pagina'] - 1) * self::POR_PAGINA, self::POR_PAGINA);
            }
        }
        unset($tabla);

        $this->vista('admin/reportes/reporte', [
            'seccionActiva' => 'reportes',
            'seccion'       => $seccion,
            'secciones'     => self::SECCIONES,
            'esAdmin'       => $esAdmin,
            'periodo'       => $periodo,
            'reporte'       => $reporte,
            'consulta'      => $this->consultaActual(),
            'generado'      => date('d/m/Y H:i'),
            'usuarioNombre' => (string) $usuario['nombre'],
            'nombreSitio'   => (string) (new Configuracion())->obtenerTodas()['nombre_sitio'],
        ]);
    }

    /** POST: registra en Auditoría que se imprimió un reporte (la impresión la hace el navegador). */
    public function registrarImpresion(): void
    {
        $this->requerirPermiso('reportes', 'ver');
        header('Content-Type: application/json; charset=utf-8');

        $seccion = Entrada::texto($_POST, 'seccion', '');
        if (isset(self::SECCIONES[$seccion])) {
            $periodo = ReportePeriodo::resolver($_POST);
            AuditoriaService::registrar('reportes', 0, 'editar', null, [
                'detalle' => 'Imprimió el reporte «' . self::SECCIONES[$seccion][0] . '» (' . $periodo['etiqueta'] . ')',
            ]);
        }
        echo '{"ok":true}';
    }

    // ------------------------------------------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function construir(string $seccion, array $periodo, bool $esAdmin, int $usuarioId): array
    {
        $desde = $periodo['desde'];
        $hasta = $periodo['hasta'];
        $mascara = !$esAdmin;   // el Cajero ve los teléfonos enmascarados

        switch ($seccion) {
            case 'ventas':
                return $this->reporteVentas($desde, $hasta);
            case 'pedidos':
                return $this->reportePedidos($desde, $hasta, $mascara);
            case 'lealtad':
                return $this->reporteLealtad($desde, $hasta, $mascara, 0);
            case 'consumos':
                return $this->reporteLealtad($desde, $hasta, $mascara, $usuarioId);
            case 'clientes':
                return $this->reporteClientes($desde, $hasta);
            case 'ajustes':
                return $this->reporteAjustes($desde, $hasta);
            case 'catalogo':
                return $this->reporteCatalogo();
            case 'verificacion':
                return $this->reporteVerificacion();
            case 'resumen':
            default:
                return $this->reporteResumen($desde, $hasta);
        }
    }

    /** @return array<string,mixed> */
    private function reporteResumen(string $desde, string $hasta): array
    {
        $r = VentasService::resumen($desde, $hasta);
        $dias = VentasService::porDia($desde, $hasta);

        $puntos = [];
        foreach ($dias as $d) {
            $puntos[] = ['etiqueta' => $d['fecha'], 'valor' => $d['ventas_centavos']];
        }

        return [
            'titulo'   => 'Resumen',
            'ayuda'    => 'Ventas = pedidos ENTREGADOS, contados en el día en que se entregaron. Los pedidos nuevos, confirmados, cancelados o anulados no suman. Incluye solo pedidos hechos desde la página web.',
            'tarjetas' => $this->tarjetasVentas($r),
            'grafico'  => ['titulo' => 'Ventas por día', 'puntos' => $puntos],
            'tablas'   => [],
        ];
    }

    /** @param array<string,int> $r @return array<int,array{etiqueta:string,valor:string,nota:string}> */
    private function tarjetasVentas(array $r): array
    {
        $tasa = Dinero::decimal($r['tasa_cancelacion']) . ' %';

        return [
            ['etiqueta' => 'Ventas totales', 'valor' => Dinero::formato($r['ventas_centavos']), 'nota' => 'Pedidos entregados en el periodo'],
            ['etiqueta' => 'Pedidos entregados', 'valor' => (string) $r['pedidos'], 'nota' => 'Número de ventas'],
            ['etiqueta' => 'Ticket promedio', 'valor' => Dinero::formato($r['ticket_promedio_centavos']), 'nota' => 'Ventas ÷ pedidos entregados'],
            ['etiqueta' => 'Pedidos pendientes', 'valor' => (string) $r['pendientes'], 'nota' => 'Nuevos o confirmados, creados en el periodo'],
            ['etiqueta' => 'Cancelados', 'valor' => (string) $r['cancelados'], 'nota' => 'Creados en el periodo'],
            ['etiqueta' => 'Anulados', 'valor' => (string) $r['anulados'], 'nota' => 'Entregados que luego se anularon (creados en el periodo)'],
            ['etiqueta' => 'Tasa de cancelación', 'valor' => $tasa, 'nota' => 'Cancelados ÷ pedidos creados en el periodo (' . $r['creados'] . ')'],
        ];
    }

    /** @return array<string,mixed> */
    private function reporteVentas(string $desde, string $hasta): array
    {
        $r = VentasService::resumen($desde, $hasta);
        $dias = VentasService::porDia($desde, $hasta);
        $puntos = [];
        $filasDias = [];
        foreach ($dias as $d) {
            $puntos[] = ['etiqueta' => $d['fecha'], 'valor' => $d['ventas_centavos']];
            $filasDias[] = ['fecha' => $d['fecha'], 'pedidos' => $d['pedidos'], 'ventas' => $d['ventas_centavos']];
        }

        $masVendidos = [];
        foreach (VentasService::masVendidos($desde, $hasta) as $m) {
            $masVendidos[] = ['producto' => $m['producto'], 'categoria' => $m['categoria'], 'cantidad' => $m['cantidad'], 'ingreso' => $m['ingreso_centavos']];
        }
        $categorias = [];
        foreach (VentasService::porCategoria($desde, $hasta) as $c) {
            $categorias[] = ['categoria' => $c['categoria'], 'cantidad' => $c['cantidad'], 'ingreso' => $c['ingreso_centavos']];
        }
        $pagos = [];
        foreach (VentasService::porFormaPago($desde, $hasta) as $p) {
            $pagos[] = ['forma' => Pedido::ETIQUETAS_PAGO[$p['forma_pago']] ?? $p['forma_pago'], 'pedidos' => $p['pedidos'], 'ventas' => $p['ventas_centavos']];
        }
        $horas = [];
        foreach (VentasService::horasPico($desde, $hasta) as $h) {
            $horas[] = ['hora' => sprintf('%02d:00 a %02d:59', $h['hora'], $h['hora']), 'pedidos' => $h['pedidos'], 'ventas' => $h['ventas_centavos']];
        }

        $pieDinero = function (array $filas, string $clave): int {
            $t = 0;
            foreach ($filas as $f) {
                $t += (int) $f[$clave];
            }

            return $t;
        };

        return [
            'titulo'   => 'Ventas',
            'ayuda'    => 'Ventas = pedidos ENTREGADOS, contados en el día en que se entregaron. Incluye solo pedidos hechos desde la página web.',
            'tarjetas' => $this->tarjetasVentas($r),
            'grafico'  => ['titulo' => 'Ventas por día', 'puntos' => $puntos],
            'tablas'   => [
                'por_dia' => [
                    'titulo' => 'Ventas por día', 'nota' => 'Incluye los días sin ventas (en 0).',
                    'columnas' => [['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'texto'], ['clave' => 'pedidos', 'etiqueta' => 'Pedidos', 'tipo' => 'entero'], ['clave' => 'ventas', 'etiqueta' => 'Ventas (Q)', 'tipo' => 'dinero']],
                    'filas' => $filasDias,
                    'pie' => ['fecha' => 'Total', 'pedidos' => $pieDinero($filasDias, 'pedidos'), 'ventas' => $pieDinero($filasDias, 'ventas')],
                ],
                'mas_vendidos' => [
                    'titulo' => 'Productos más vendidos', 'nota' => 'Cantidad e ingreso con el nombre y precio de cada pedido; las cortesías no suman ingreso.',
                    'columnas' => [['clave' => 'producto', 'etiqueta' => 'Producto', 'tipo' => 'texto'], ['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'], ['clave' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'entero'], ['clave' => 'ingreso', 'etiqueta' => 'Ingreso (Q)', 'tipo' => 'dinero']],
                    'filas' => $masVendidos,
                    'pie' => ['producto' => 'Total', 'cantidad' => $pieDinero($masVendidos, 'cantidad'), 'ingreso' => $pieDinero($masVendidos, 'ingreso')],
                    'paginar' => true,
                ],
                'por_categoria' => [
                    'titulo' => 'Ventas por categoría',
                    'columnas' => [['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'], ['clave' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'entero'], ['clave' => 'ingreso', 'etiqueta' => 'Ingreso (Q)', 'tipo' => 'dinero']],
                    'filas' => $categorias,
                    'pie' => ['categoria' => 'Total', 'cantidad' => $pieDinero($categorias, 'cantidad'), 'ingreso' => $pieDinero($categorias, 'ingreso')],
                ],
                'por_pago' => [
                    'titulo' => 'Ventas por forma de pago',
                    'columnas' => [['clave' => 'forma', 'etiqueta' => 'Forma de pago', 'tipo' => 'texto'], ['clave' => 'pedidos', 'etiqueta' => 'Pedidos', 'tipo' => 'entero'], ['clave' => 'ventas', 'etiqueta' => 'Ventas (Q)', 'tipo' => 'dinero']],
                    'filas' => $pagos,
                    'pie' => ['forma' => 'Total', 'pedidos' => $pieDinero($pagos, 'pedidos'), 'ventas' => $pieDinero($pagos, 'ventas')],
                ],
                'horas_pico' => [
                    'titulo' => 'Horas pico', 'nota' => 'Hora en que se entregó el pedido (hora de Guatemala).',
                    'columnas' => [['clave' => 'hora', 'etiqueta' => 'Hora', 'tipo' => 'texto'], ['clave' => 'pedidos', 'etiqueta' => 'Pedidos', 'tipo' => 'entero'], ['clave' => 'ventas', 'etiqueta' => 'Ventas (Q)', 'tipo' => 'dinero']],
                    'filas' => $horas,
                    'pie' => ['hora' => 'Total', 'pedidos' => $pieDinero($horas, 'pedidos'), 'ventas' => $pieDinero($horas, 'ventas')],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function reportePedidos(string $desde, string $hasta, bool $mascara): array
    {
        $estado = Entrada::texto($_GET, 'estado', '');
        $par = ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];
        $extra = '';
        if (in_array($estado, Pedido::ESTADOS, true)) {
            $extra = ' AND p.estado = :estado';
            $par['estado'] = $estado;
        } else {
            $estado = '';
            $extra = " AND p.estado <> 'pendiente'";   // los «sin confirmar» solo salen si se piden
        }

        $stmt = Database::conexion()->prepare(
            "SELECT p.codigo, p.creado_en, p.entregado_en, p.cliente_nombre, p.cliente_telefono, p.forma_pago, p.estado,
                    p.total_centavos
             FROM pedidos p WHERE p.creado_en BETWEEN :desde AND :hasta{$extra}
             ORDER BY p.creado_en DESC, p.id DESC"
        );
        $stmt->execute($par);

        $filas = [];
        $total = 0;
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = [
                'codigo'   => $f['codigo'],
                'creado'   => substr((string) $f['creado_en'], 0, 16),
                'entregado' => $f['entregado_en'] !== null ? substr((string) $f['entregado_en'], 0, 16) : '',
                'cliente'  => (string) ($f['cliente_nombre'] !== null ? $f['cliente_nombre'] : ''),
                'telefono' => self::telefono((string) ($f['cliente_telefono'] !== null ? $f['cliente_telefono'] : ''), $mascara),
                'pago'     => Pedido::ETIQUETAS_PAGO[$f['forma_pago']] ?? $f['forma_pago'],
                'estado'   => Pedido::ETIQUETAS_ESTADO[$f['estado']] ?? $f['estado'],
                'total'    => (int) $f['total_centavos'],
            ];
            $total += (int) $f['total_centavos'];
        }

        return [
            'titulo'   => 'Pedidos',
            'ayuda'    => 'Pedidos CREADOS en el periodo, con su estado actual. (Las ventas del periodo salen de «Ventas»: pedidos entregados en esas fechas.)',
            'filtros'  => [['nombre' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select', 'valor' => $estado,
                            'opciones' => array_merge(['' => 'Todos'], Pedido::ETIQUETAS_ESTADO)]],
            'tarjetas' => [],
            'grafico'  => null,
            'tablas'   => [
                'pedidos' => [
                    'titulo' => 'Pedidos', 'paginar' => true,
                    'columnas' => [
                        ['clave' => 'codigo', 'etiqueta' => 'Código', 'tipo' => 'texto'], ['clave' => 'creado', 'etiqueta' => 'Creado', 'tipo' => 'texto'],
                        ['clave' => 'entregado', 'etiqueta' => 'Entregado', 'tipo' => 'texto'], ['clave' => 'cliente', 'etiqueta' => 'Cliente', 'tipo' => 'texto'],
                        ['clave' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'texto'], ['clave' => 'pago', 'etiqueta' => 'Forma de pago', 'tipo' => 'texto'],
                        ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'texto'], ['clave' => 'total', 'etiqueta' => 'Total (Q)', 'tipo' => 'dinero'],
                    ],
                    'filas' => $filas,
                    'pie' => ['codigo' => 'Total (todos los estados)', 'total' => $total],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function reporteLealtad(string $desde, string $hasta, bool $mascara, int $soloUsuario): array
    {
        $propio = $soloUsuario > 0;
        $tipo = Entrada::texto($_GET, 'tipo', '');
        if (!isset(ReporteLealtadService::TIPOS_MOVIMIENTO[$tipo])) {
            $tipo = '';
        }
        $agrupar = Entrada::texto($_GET, 'agrupar', 'dia');
        if (!in_array($agrupar, ['dia', 'semana', 'mes', 'usuario'], true)) {
            $agrupar = 'dia';
        }

        $movs = [];
        $cafes = 0;
        foreach (ReporteLealtadService::movimientos($desde, $hasta, ['tipo' => $tipo, 'solo_usuario_id' => $soloUsuario]) as $m) {
            $movs[] = [
                'fecha'    => substr((string) $m['creado_en'], 0, 16),
                'cliente'  => $m['cliente_nombre'],
                'telefono' => self::telefono((string) $m['cliente_telefono'], $mascara),
                'tipo'     => ReporteLealtadService::TIPOS_MOVIMIENTO[$m['tipo']] ?? $m['tipo'],
                'cantidad' => (int) $m['cantidad'],
                'motivo'   => (string) ($m['motivo'] !== null ? $m['motivo'] : ($m['referencia'] !== null ? 'Código ' . $m['referencia'] : '')),
                'usuario'  => (string) ($m['usuario_nombre'] !== null ? $m['usuario_nombre'] : 'Sistema'),
            ];
            if ($m['tipo'] === 'consumo') {
                $cafes += (int) $m['cantidad'];
            }
        }

        $tarjetas = [];
        if (!$propio) {
            $r = ReporteLealtadService::resumen($desde, $hasta);
            $tarjetas = [
                ['etiqueta' => 'Clientes nuevos', 'valor' => (string) $r['clientes_nuevos'], 'nota' => ''],
                ['etiqueta' => 'Consumos registrados', 'valor' => (string) $r['consumos'], 'nota' => ''],
                ['etiqueta' => 'Cafés acumulados', 'valor' => (string) $r['cafes_acumulados'], 'nota' => 'Suma de los consumos'],
                ['etiqueta' => 'Recompensas generadas', 'valor' => (string) $r['recompensas'], 'nota' => ''],
                ['etiqueta' => 'Canjes', 'valor' => (string) $r['canjes'], 'nota' => 'Recompensas canjeadas'],
                ['etiqueta' => 'Ajustes manuales', 'valor' => (string) $r['ajustes'], 'nota' => '+' . $r['ajustes_sumados'] . ' / −' . $r['ajustes_restados'] . ' cafés'],
                ['etiqueta' => 'Cupones reclamados', 'valor' => (string) $r['cupones_reclamados'], 'nota' => ''],
                ['etiqueta' => 'Cupones canjeados', 'valor' => (string) $r['cupones_canjeados'], 'nota' => ''],
            ];
        }

        $tablas = [];
        if (!$propio) {
            $agr = [];
            foreach (ReporteLealtadService::agrupado($desde, $hasta, $agrupar) as $g) {
                $agr[] = ['grupo' => $g['grupo'], 'movimientos' => $g['movimientos'], 'cafes' => $g['cafes'], 'canjes' => $g['canjes']];
            }
            $tablas['agrupado'] = [
                'titulo' => 'Consumos y canjes por ' . ['dia' => 'día', 'semana' => 'semana (lunes)', 'mes' => 'mes', 'usuario' => 'usuario'][$agrupar],
                'columnas' => [['clave' => 'grupo', 'etiqueta' => ['dia' => 'Día', 'semana' => 'Semana del', 'mes' => 'Mes', 'usuario' => 'Usuario'][$agrupar], 'tipo' => 'texto'],
                               ['clave' => 'movimientos', 'etiqueta' => 'Movimientos', 'tipo' => 'entero'], ['clave' => 'cafes', 'etiqueta' => 'Cafés', 'tipo' => 'entero'], ['clave' => 'canjes', 'etiqueta' => 'Canjes', 'tipo' => 'entero']],
                'filas' => $agr,
                'pie' => ['grupo' => 'Total', 'movimientos' => array_sum(array_column($agr, 'movimientos')), 'cafes' => array_sum(array_column($agr, 'cafes')), 'canjes' => array_sum(array_column($agr, 'canjes'))],
            ];
        }
        $tablas['movimientos'] = [
            'titulo' => $propio ? 'Consumos y canjes que registré' : 'Movimientos de lealtad (cronológico)', 'paginar' => true,
            'columnas' => [
                ['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'texto'], ['clave' => 'cliente', 'etiqueta' => 'Cliente', 'tipo' => 'texto'],
                ['clave' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'texto'], ['clave' => 'tipo', 'etiqueta' => 'Tipo', 'tipo' => 'texto'],
                ['clave' => 'cantidad', 'etiqueta' => 'Cantidad', 'tipo' => 'entero'], ['clave' => 'motivo', 'etiqueta' => 'Motivo / código', 'tipo' => 'texto'],
                ['clave' => 'usuario', 'etiqueta' => 'Registrado por', 'tipo' => 'texto'],
            ],
            'filas' => $movs,
            'pie' => ['fecha' => 'Cafés en consumos', 'cantidad' => $cafes],
        ];

        $filtros = [['nombre' => 'tipo', 'etiqueta' => 'Tipo de movimiento', 'tipo' => 'select', 'valor' => $tipo,
                     'opciones' => array_merge(['' => 'Todos'], ReporteLealtadService::TIPOS_MOVIMIENTO)]];
        if (!$propio) {
            $filtros[] = ['nombre' => 'agrupar', 'etiqueta' => 'Agrupar por', 'tipo' => 'select', 'valor' => $agrupar,
                          'opciones' => ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes', 'usuario' => 'Usuario']];
        }

        return [
            'titulo'   => $propio ? 'Mis consumos' : 'Lealtad',
            'ayuda'    => $propio ? 'Solo los consumos y canjes que registraste tú.' : 'Resumen del programa de lealtad y movimientos del periodo.',
            'filtros'  => $filtros,
            'tarjetas' => $tarjetas,
            'grafico'  => null,
            'tablas'   => $tablas,
        ];
    }

    /** @return array<string,mixed> */
    private function reporteClientes(string $desde, string $hasta): array
    {
        $umbral = LealtadService::obtenerUmbral();
        $filtro = Entrada::texto($_GET, 'filtro', '');
        if (!in_array($filtro, ['cerca', 'pendiente', 'inactivos30', 'inactivos60', 'inactivos90', 'top10'], true)) {
            $filtro = '';
        }
        $orden = Entrada::texto($_GET, 'orden', 'nombre');
        if (!in_array($orden, ['nombre', 'saldo', 'historico', 'ultimo', 'canjes'], true)) {
            $orden = 'nombre';
        }
        $q = mb_substr(trim(Entrada::texto($_GET, 'q', '')), 0, 40);

        $filas = [];
        foreach (ReporteLealtadService::clientes(['q' => $q, 'filtro' => $filtro, 'orden' => $orden], $umbral, $desde, $hasta) as $c) {
            $filas[] = [
                'nombre'     => $c['nombre'],
                'telefono'   => $c['telefono'],
                'saldo'      => (int) $c['saldo'] . ' / ' . $umbral,
                'historico'  => (int) $c['historico'],
                'canjes'     => (int) $c['canjes'],
                'disponibles' => (int) $c['disponibles'],
                'ultimo'     => $c['ultimo_consumo'] !== null ? substr((string) $c['ultimo_consumo'], 0, 10) : 'Nunca',
                'periodo'    => (int) $c['cafes_periodo'],
            ];
        }

        $columnas = [
            ['clave' => 'nombre', 'etiqueta' => 'Cliente', 'tipo' => 'texto'], ['clave' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'texto'],
            ['clave' => 'saldo', 'etiqueta' => 'Saldo actual', 'tipo' => 'texto'], ['clave' => 'historico', 'etiqueta' => 'Cafés (histórico)', 'tipo' => 'entero'],
            ['clave' => 'canjes', 'etiqueta' => 'Canjes', 'tipo' => 'entero'], ['clave' => 'disponibles', 'etiqueta' => 'Recompensas pendientes', 'tipo' => 'entero'],
            ['clave' => 'ultimo', 'etiqueta' => 'Último consumo', 'tipo' => 'texto'],
        ];
        if ($filtro === 'top10') {
            $columnas[] = ['clave' => 'periodo', 'etiqueta' => 'Cafés en el periodo', 'tipo' => 'entero'];
        }

        return [
            'titulo'   => 'Clientes',
            'ayuda'    => 'Saldo = cafés acumulados hacia la próxima recompensa (umbral: ' . $umbral . '). «Top 10» usa el periodo elegido arriba.',
            'filtros'  => [
                ['nombre' => 'q', 'etiqueta' => 'Buscar (nombre o teléfono)', 'tipo' => 'texto', 'valor' => $q],
                ['nombre' => 'filtro', 'etiqueta' => 'Mostrar', 'tipo' => 'select', 'valor' => $filtro, 'opciones' => [
                    '' => 'Todos', 'cerca' => 'Cerca de la recompensa (' . max(1, $umbral - 2) . '-' . max(1, $umbral - 1) . ')',
                    'pendiente' => 'Con recompensa pendiente', 'inactivos30' => 'Inactivos 30 días', 'inactivos60' => 'Inactivos 60 días',
                    'inactivos90' => 'Inactivos 90 días', 'top10' => 'Top 10 del periodo']],
                ['nombre' => 'orden', 'etiqueta' => 'Ordenar por', 'tipo' => 'select', 'valor' => $orden, 'opciones' => [
                    'nombre' => 'Nombre', 'saldo' => 'Saldo', 'historico' => 'Histórico', 'ultimo' => 'Último consumo', 'canjes' => 'Canjes']],
            ],
            'tarjetas' => [],
            'grafico'  => null,
            'tablas'   => ['clientes' => ['titulo' => 'Clientes', 'paginar' => true, 'columnas' => $columnas, 'filas' => $filas]],
        ];
    }

    /** @return array<string,mixed> */
    private function reporteAjustes(string $desde, string $hasta): array
    {
        $usuarioId = (int) Entrada::texto($_GET, 'usuario', '0');
        $accion = Entrada::texto($_GET, 'accion', '');

        $usuarios = ['0' => 'Todos'];
        foreach (Database::conexion()->query('SELECT id, nombre FROM usuarios ORDER BY nombre')->fetchAll() as $u) {
            $usuarios[(string) $u['id']] = $u['nombre'];
        }
        if (!isset($usuarios[(string) $usuarioId])) {
            $usuarioId = 0;
        }

        $ajustes = [];
        $neto = 0;
        foreach (ReporteLealtadService::ajustes($desde, $hasta, $usuarioId) as $a) {
            $signo = $a['tipo'] === 'ajuste_negativo' ? -1 : 1;
            $ajustes[] = [
                'fecha'    => substr((string) $a['creado_en'], 0, 16),
                'usuario'  => (string) ($a['usuario_nombre'] !== null ? $a['usuario_nombre'] : 'Sistema'),
                'cliente'  => $a['cliente_nombre'],
                'telefono' => $a['cliente_telefono'],
                'cantidad' => $signo * (int) $a['cantidad'],
                'motivo'   => (string) $a['motivo'],
            ];
            $neto += $signo * (int) $a['cantidad'];
        }

        $acciones = [];
        foreach (ReporteLealtadService::acciones($desde, $hasta, $usuarioId, $accion) as $b) {
            $acciones[] = [
                'fecha'   => substr((string) $b['creado_en'], 0, 16),
                'usuario' => AuditoriaTexto::usuario($b),
                'modulo'  => AuditoriaTexto::modulo((string) $b['tabla_afectada']),
                'accion'  => ['crear' => 'Creación', 'editar' => 'Edición', 'eliminar' => 'Eliminación', 'ajuste' => 'Ajuste', 'canje' => 'Canje'][$b['accion']] ?? $b['accion'],
                'detalle' => AuditoriaTexto::describir($b),
            ];
        }

        return [
            'titulo'   => 'Ajustes y auditoría',
            'ayuda'    => 'Ajustes manuales de cafés y otras acciones sensibles registradas en Auditoría (no incluye el registro normal de consumos).',
            'filtros'  => [
                ['nombre' => 'usuario', 'etiqueta' => 'Usuario', 'tipo' => 'select', 'valor' => (string) $usuarioId, 'opciones' => $usuarios],
                ['nombre' => 'accion', 'etiqueta' => 'Tipo de acción', 'tipo' => 'select', 'valor' => $accion,
                 'opciones' => ['' => 'Todas', 'crear' => 'Creación', 'editar' => 'Edición', 'eliminar' => 'Eliminación', 'ajuste' => 'Ajuste', 'canje' => 'Canje']],
            ],
            'tarjetas' => [],
            'grafico'  => null,
            'tablas'   => [
                'ajustes' => [
                    'titulo' => 'Ajustes manuales de cafés', 'paginar' => true,
                    'columnas' => [['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'texto'], ['clave' => 'usuario', 'etiqueta' => 'Quién', 'tipo' => 'texto'],
                                   ['clave' => 'cliente', 'etiqueta' => 'Cliente', 'tipo' => 'texto'], ['clave' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'texto'],
                                   ['clave' => 'cantidad', 'etiqueta' => 'Cantidad (±)', 'tipo' => 'entero'], ['clave' => 'motivo', 'etiqueta' => 'Motivo', 'tipo' => 'texto']],
                    'filas' => $ajustes,
                    'pie' => ['fecha' => 'Neto', 'cantidad' => $neto],
                ],
                'acciones' => [
                    'titulo' => 'Otras acciones sensibles', 'paginar' => true,
                    'columnas' => [['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'texto'], ['clave' => 'usuario', 'etiqueta' => 'Quién', 'tipo' => 'texto'],
                                   ['clave' => 'modulo', 'etiqueta' => 'Módulo', 'tipo' => 'texto'], ['clave' => 'accion', 'etiqueta' => 'Acción', 'tipo' => 'texto'],
                                   ['clave' => 'detalle', 'etiqueta' => 'Qué pasó', 'tipo' => 'texto']],
                    'filas' => $acciones,
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function reporteCatalogo(): array
    {
        $estado = Entrada::texto($_GET, 'estado_promo', '');
        $menu = Entrada::texto($_GET, 'menu', '');
        if (!in_array($menu, ['sin_foto', 'inactivos'], true)) {
            $menu = '';
        }

        $promos = [];
        foreach (ReporteLealtadService::promociones(date('Y-m-d')) as $p) {
            if (in_array($estado, ['Vigente', 'Próxima', 'Vencida', 'Inactiva'], true) && $p['estado_texto'] !== $estado) {
                continue;
            }
            $promos[] = [
                'nombre' => $p['nombre'], 'estado' => $p['estado_texto'],
                'vigencia' => $p['fecha_inicio'] . ' al ' . $p['fecha_fin'],
                'lealtad' => (int) $p['combinable_con_lealtad'] === 1 ? 'Sí' : 'No',
                'reclamados' => (int) $p['reclamados'], 'canjeados' => (int) $p['canjeados'],
                'cancelados' => (int) $p['cancelados'], 'vencidos' => (int) $p['vencidos'],
            ];
        }

        $productos = [];
        foreach (ReporteLealtadService::menu($menu) as $m) {
            $productos[] = [
                'categoria' => $m['categoria'] . ((int) $m['categoria_activa'] === 0 ? ' (inactiva)' : ''),
                'nombre'    => $m['nombre'],
                'precio'    => Dinero::aCentavos((string) $m['precio']),
                'precio_alt' => $m['precio_alt'] !== null ? Dinero::aCentavos((string) $m['precio_alt']) : null,
                'estado'    => (int) $m['activo'] === 1 ? 'Activo' : 'Inactivo',
                'foto'      => (int) $m['tiene_foto'] === 1 ? 'Con foto' : 'Sin foto',
                'marca'     => trim(((int) $m['es_destacado'] === 1 ? 'Destacado ' : '') . ((int) $m['es_mas_vendido'] === 1 ? 'Más vendido' : '')),
            ];
        }

        return [
            'titulo'      => 'Promociones y menú',
            'ayuda'       => 'Estado actual (no depende del periodo).',
            'usaPeriodo'  => false,
            'filtros'     => [
                ['nombre' => 'estado_promo', 'etiqueta' => 'Promociones', 'tipo' => 'select', 'valor' => $estado,
                 'opciones' => ['' => 'Todas', 'Vigente' => 'Vigentes', 'Próxima' => 'Próximas', 'Vencida' => 'Vencidas', 'Inactiva' => 'Inactivas']],
                ['nombre' => 'menu', 'etiqueta' => 'Menú', 'tipo' => 'select', 'valor' => $menu,
                 'opciones' => ['' => 'Todos los productos', 'sin_foto' => 'Sin foto', 'inactivos' => 'Inactivos']],
            ],
            'tarjetas'    => [],
            'grafico'     => null,
            'tablas'      => [
                'promociones' => [
                    'titulo' => 'Promociones',
                    'columnas' => [['clave' => 'nombre', 'etiqueta' => 'Promoción', 'tipo' => 'texto'], ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'texto'],
                                   ['clave' => 'vigencia', 'etiqueta' => 'Vigencia', 'tipo' => 'texto'], ['clave' => 'lealtad', 'etiqueta' => 'Con lealtad', 'tipo' => 'texto'],
                                   ['clave' => 'reclamados', 'etiqueta' => 'Cupones reclamados', 'tipo' => 'entero'], ['clave' => 'canjeados', 'etiqueta' => 'Canjeados', 'tipo' => 'entero'],
                                   ['clave' => 'cancelados', 'etiqueta' => 'Cancelados', 'tipo' => 'entero'], ['clave' => 'vencidos', 'etiqueta' => 'Vencidos', 'tipo' => 'entero']],
                    'filas' => $promos,
                ],
                'menu' => [
                    'titulo' => 'Productos por categoría', 'paginar' => true,
                    'columnas' => [['clave' => 'categoria', 'etiqueta' => 'Categoría', 'tipo' => 'texto'], ['clave' => 'nombre', 'etiqueta' => 'Producto', 'tipo' => 'texto'],
                                   ['clave' => 'precio', 'etiqueta' => 'Precio (Q)', 'tipo' => 'dinero'], ['clave' => 'precio_alt', 'etiqueta' => 'Precio alterno (Q)', 'tipo' => 'dinero'],
                                   ['clave' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'texto'], ['clave' => 'foto', 'etiqueta' => 'Foto', 'tipo' => 'texto'],
                                   ['clave' => 'marca', 'etiqueta' => 'Destacado', 'tipo' => 'texto']],
                    'filas' => $productos,
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function reporteVerificacion(): array
    {
        $resultado = VerificacionVentas::ejecutar();

        $filas = [];
        foreach ($resultado['hallazgos'] as $h) {
            $filas[] = ['revision' => $h['revision'], 'detalle' => $h['detalle']];
        }
        $revisiones = [];
        foreach ($resultado['revisiones'] as $nombre => $n) {
            $revisiones[] = ['revision' => $nombre, 'detalle' => $n === 0 ? 'Sin inconsistencias' : $n . ' inconsistencia(s)'];
        }

        return [
            'titulo'     => 'Verificación de datos',
            'ayuda'      => 'Comprueba que los números de los pedidos cuadren entre sí.',
            'usaPeriodo' => false,
            'mensaje'    => ['tipo' => $filas === [] ? 'exito' : 'error', 'texto' => $filas === [] ? 'TODO CUADRA (' . $resultado['pedidos'] . ' pedidos revisados).' : count($filas) . ' inconsistencia(s) encontradas.'],
            'tarjetas'   => [],
            'grafico'    => null,
            'tablas'     => [
                'revisiones' => ['titulo' => 'Revisiones', 'columnas' => [['clave' => 'revision', 'etiqueta' => 'Revisión', 'tipo' => 'texto'], ['clave' => 'detalle', 'etiqueta' => 'Resultado', 'tipo' => 'texto']], 'filas' => $revisiones],
                'hallazgos'  => ['titulo' => 'Inconsistencias', 'columnas' => [['clave' => 'revision', 'etiqueta' => 'Revisión', 'tipo' => 'texto'], ['clave' => 'detalle', 'etiqueta' => 'Detalle', 'tipo' => 'texto']], 'filas' => $filas],
            ],
        ];
    }

    // ------------------------------------------------------------------------------------------------------------

    /** @param array<string,mixed> $reporte */
    private function exportar(string $seccion, array $reporte, array $periodo): void
    {
        $tablaId = Entrada::texto($_GET, 'tabla', '');
        if (!isset($reporte['tablas'][$tablaId])) {
            $claves = array_keys($reporte['tablas']);
            if ($claves === []) {
                // El Resumen no tiene tablas: se exporta la serie de ventas por día.
                $dias = VentasService::porDia($periodo['desde'], $periodo['hasta']);
                $filas = [];
                foreach ($dias as $d) {
                    $filas[] = ['fecha' => $d['fecha'], 'pedidos' => $d['pedidos'], 'ventas' => $d['ventas_centavos']];
                }
                $reporte['tablas'] = ['por_dia' => ['columnas' => [['clave' => 'fecha', 'etiqueta' => 'Fecha', 'tipo' => 'texto'], ['clave' => 'pedidos', 'etiqueta' => 'Pedidos', 'tipo' => 'entero'], ['clave' => 'ventas', 'etiqueta' => 'Ventas (Q)', 'tipo' => 'dinero']], 'filas' => $filas]];
                $claves = ['por_dia'];
            }
            $tablaId = $claves[0];
        }
        $tabla = $reporte['tablas'][$tablaId];

        AuditoriaService::registrar('reportes', 0, 'editar', null, [
            'detalle' => 'Exportó a Excel el reporte «' . self::SECCIONES[$seccion][0] . '» — ' . (isset($tabla['titulo']) ? $tabla['titulo'] : $tablaId)
                . ' (' . $periodo['etiqueta'] . ', ' . count($tabla['filas']) . ' filas)',
        ]);

        $base = 'reporte_' . $seccion . '_' . $tablaId . '_' . $periodo['desde'] . '_a_' . $periodo['hasta'];
        Xlsx::enviar($base . '.xlsx', isset($tabla['titulo']) ? (string) $tabla['titulo'] : self::SECCIONES[$seccion][0], $tabla['columnas'], $tabla['filas']);
        exit;
    }

    /** Enmascara un teléfono para el Cajero: 33577968 → 3357**** */
    private static function telefono(string $telefono, bool $mascara): string
    {
        if (!$mascara || $telefono === '') {
            return $telefono;
        }

        return substr($telefono, 0, 4) . str_repeat('*', max(0, strlen($telefono) - 4));
    }

    /** Parámetros actuales de la URL (sin pagina ni formato) para armar enlaces. @return array<string,string> */
    private function consultaActual(): array
    {
        $q = [];
        foreach ($_GET as $k => $v) {
            if (is_string($v) && $v !== '' && !in_array($k, ['pagina', 'formato', 'tabla'], true)) {
                $q[$k] = $v;
            }
        }

        return $q;
    }
}
