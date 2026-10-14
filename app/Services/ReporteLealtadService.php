<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Consultas de los reportes de Lealtad, Clientes, Ajustes, Promociones y Menú. Solo lectura, sentencias
 * preparadas. Las listas largas devuelven TODO el resultado filtrado (la pantalla pagina, el CSV no).
 * Compatible con PHP 7.4.
 */
class ReporteLealtadService
{
    public const TIPOS_MOVIMIENTO = [
        'consumo'             => 'Consumo',
        'ajuste_positivo'     => 'Ajuste (+)',
        'ajuste_negativo'     => 'Ajuste (−)',
        'recompensa_generada' => 'Recompensa generada',
        'recompensa_canjeada' => 'Recompensa canjeada',
        'recompensa_cancelada' => 'Recompensa cancelada',
    ];

    /** @return array<string,int> */
    public static function resumen(string $desde, string $hasta): array
    {
        $pdo = Database::conexion();
        $p = ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];

        $uno = function (string $sql) use ($pdo, $p): array {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($p);

            return $stmt->fetch();
        };

        $nuevos = $uno('SELECT COUNT(*) AS n FROM clientes WHERE creado_en BETWEEN :desde AND :hasta');
        $consumos = $uno("SELECT COUNT(*) AS n, COALESCE(SUM(cantidad), 0) AS cafes FROM lealtad_movimientos
                          WHERE tipo = 'consumo' AND estado = 'activo' AND creado_en BETWEEN :desde AND :hasta");
        $ajustes = $uno("SELECT COUNT(*) AS n,
                                COALESCE(SUM(CASE WHEN tipo = 'ajuste_positivo' THEN cantidad ELSE 0 END), 0) AS sumados,
                                COALESCE(SUM(CASE WHEN tipo = 'ajuste_negativo' THEN cantidad ELSE 0 END), 0) AS restados
                         FROM lealtad_movimientos
                         WHERE tipo IN ('ajuste_positivo', 'ajuste_negativo') AND estado = 'activo' AND creado_en BETWEEN :desde AND :hasta");
        $generadas = $uno('SELECT COUNT(*) AS n FROM recompensas WHERE fecha_generada BETWEEN :desde AND :hasta');
        $canjes = $uno("SELECT COUNT(*) AS n FROM recompensas WHERE estado = 'canjeada' AND fecha_canjeada BETWEEN :desde AND :hasta");
        $reclamados = $uno('SELECT COUNT(*) AS n FROM cupones_promocion WHERE fecha_reclamo BETWEEN :desde AND :hasta');
        $cuponesCanjeados = $uno("SELECT COUNT(*) AS n FROM cupones_promocion WHERE estado = 'canjeado' AND fecha_canje BETWEEN :desde AND :hasta");

        return [
            'clientes_nuevos'     => (int) $nuevos['n'],
            'consumos'            => (int) $consumos['n'],
            'cafes_acumulados'    => (int) $consumos['cafes'],
            'ajustes'             => (int) $ajustes['n'],
            'ajustes_sumados'     => (int) $ajustes['sumados'],
            'ajustes_restados'    => (int) $ajustes['restados'],
            'recompensas'         => (int) $generadas['n'],
            'canjes'              => (int) $canjes['n'],
            'cupones_reclamados'  => (int) $reclamados['n'],
            'cupones_canjeados'   => (int) $cuponesCanjeados['n'],
        ];
    }

    /**
     * Movimientos de lealtad en orden cronológico.
     *
     * @param array{tipo?:string,usuario_id?:int,solo_usuario_id?:int} $filtros
     * @return array<int,array<string,mixed>>
     */
    public static function movimientos(string $desde, string $hasta, array $filtros): array
    {
        $cond = ["m.creado_en BETWEEN :desde AND :hasta", "m.estado = 'activo'"];
        $par = ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];

        if (!empty($filtros['tipo']) && isset(self::TIPOS_MOVIMIENTO[$filtros['tipo']])) {
            $cond[] = 'm.tipo = :tipo';
            $par['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['usuario_id'])) {
            $cond[] = 'm.usuario_id = :usuario';
            $par['usuario'] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['solo_usuario_id'])) {
            $cond[] = 'm.usuario_id = :solo';
            $par['solo'] = (int) $filtros['solo_usuario_id'];
        }

        $stmt = Database::conexion()->prepare(
            'SELECT m.id, m.creado_en, m.tipo, m.cantidad, m.motivo, m.referencia,
                    c.nombre AS cliente_nombre, c.telefono AS cliente_telefono, u.nombre AS usuario_nombre
             FROM lealtad_movimientos m
             JOIN clientes c ON c.id = m.cliente_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE ' . implode(' AND ', $cond) . '
             ORDER BY m.creado_en ASC, m.id ASC'
        );
        $stmt->execute($par);

        return $stmt->fetchAll();
    }

    /**
     * Totales agrupados por día, semana (lunes), mes o usuario.
     *
     * @return array<int,array{grupo:string,movimientos:int,cafes:int,canjes:int}>
     */
    public static function agrupado(string $desde, string $hasta, string $agrupar): array
    {
        switch ($agrupar) {
            case 'semana':
                $expr = 'DATE_SUB(DATE(m.creado_en), INTERVAL WEEKDAY(m.creado_en) DAY)';
                break;
            case 'mes':
                $expr = "DATE_FORMAT(m.creado_en, '%Y-%m')";
                break;
            case 'usuario':
                $expr = "COALESCE(u.nombre, 'Sistema')";
                break;
            case 'dia':
            default:
                $expr = 'DATE(m.creado_en)';
        }

        $stmt = Database::conexion()->prepare(
            "SELECT {$expr} AS grupo, COUNT(*) AS movimientos,
                    COALESCE(SUM(CASE WHEN m.tipo = 'consumo' THEN m.cantidad ELSE 0 END), 0) AS cafes,
                    SUM(CASE WHEN m.tipo = 'recompensa_canjeada' THEN 1 ELSE 0 END) AS canjes
             FROM lealtad_movimientos m LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.estado = 'activo' AND m.tipo IN ('consumo', 'recompensa_canjeada') AND m.creado_en BETWEEN :desde AND :hasta
             GROUP BY grupo ORDER BY grupo ASC"
        );
        $stmt->execute(['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59']);

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = ['grupo' => (string) $f['grupo'], 'movimientos' => (int) $f['movimientos'], 'cafes' => (int) $f['cafes'], 'canjes' => (int) $f['canjes']];
        }

        return $filas;
    }

    /**
     * Clientes con su saldo, histórico y canjes.
     *
     * @param array{q?:string,filtro?:string,orden?:string} $filtros
     *        filtro: '' | cerca | pendiente | inactivos30 | inactivos60 | inactivos90 | top10
     * @return array<int,array<string,mixed>>
     */
    public static function clientes(array $filtros, int $umbral, string $desde, string $hasta): array
    {
        $par = [];
        $cond = [];
        $filtro = isset($filtros['filtro']) ? $filtros['filtro'] : '';

        if (!empty($filtros['q'])) {
            $comodin = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filtros['q']) . '%';
            $cond[] = "(c.nombre LIKE :q_nombre ESCAPE '\\\\' OR c.telefono LIKE :q_tel ESCAPE '\\\\')";
            $par['q_nombre'] = $comodin;
            $par['q_tel'] = $comodin;
        }

        $joinPeriodo = '';
        $campoPeriodo = '0 AS cafes_periodo';
        if ($filtro === 'cerca') {
            $cond[] = 'c.cafes_acumulados >= :cerca_min AND c.cafes_acumulados <= :cerca_max';
            $par['cerca_min'] = max(1, $umbral - 2);
            $par['cerca_max'] = max(1, $umbral - 1);
        } elseif ($filtro === 'pendiente') {
            $cond[] = 'COALESCE(r2.disponibles, 0) > 0';
        } elseif (in_array($filtro, ['inactivos30', 'inactivos60', 'inactivos90'], true)) {
            $dias = (int) substr($filtro, 9);
            $cond[] = '(h.ultimo IS NULL OR h.ultimo < DATE_SUB(NOW(), INTERVAL ' . $dias . ' DAY))';
        } elseif ($filtro === 'top10') {
            $joinPeriodo = "JOIN (SELECT cliente_id, SUM(cantidad) AS cafes_periodo FROM lealtad_movimientos
                                  WHERE tipo = 'consumo' AND estado = 'activo' AND creado_en BETWEEN :p_desde AND :p_hasta
                                  GROUP BY cliente_id) tp ON tp.cliente_id = c.id";
            $campoPeriodo = 'tp.cafes_periodo AS cafes_periodo';
            $par['p_desde'] = $desde . ' 00:00:00';
            $par['p_hasta'] = $hasta . ' 23:59:59';
        }

        $orden = isset($filtros['orden']) ? $filtros['orden'] : 'nombre';
        $ordenes = [
            'nombre'    => 'c.nombre ASC',
            'saldo'     => 'c.cafes_acumulados DESC, c.nombre ASC',
            'historico' => 'historico DESC, c.nombre ASC',
            'ultimo'    => 'h.ultimo IS NULL ASC, h.ultimo DESC, c.nombre ASC',
            'canjes'    => 'canjes DESC, c.nombre ASC',
        ];
        $orderBy = $filtro === 'top10' ? 'cafes_periodo DESC, c.nombre ASC LIMIT 10' : (isset($ordenes[$orden]) ? $ordenes[$orden] : $ordenes['nombre']);

        $sql = "SELECT c.id, c.nombre, c.telefono, c.cafes_acumulados AS saldo, c.creado_en,
                       COALESCE(h.historico, 0) AS historico, COALESCE(r.canjes, 0) AS canjes,
                       COALESCE(r2.disponibles, 0) AS disponibles, h.ultimo AS ultimo_consumo, {$campoPeriodo}
                FROM clientes c
                LEFT JOIN (SELECT cliente_id, SUM(cantidad) AS historico, MAX(creado_en) AS ultimo
                           FROM lealtad_movimientos WHERE tipo = 'consumo' AND estado = 'activo' GROUP BY cliente_id) h ON h.cliente_id = c.id
                LEFT JOIN (SELECT cliente_id, COUNT(*) AS canjes FROM recompensas WHERE estado = 'canjeada' GROUP BY cliente_id) r ON r.cliente_id = c.id
                LEFT JOIN (SELECT cliente_id, COUNT(*) AS disponibles FROM recompensas WHERE estado = 'disponible' GROUP BY cliente_id) r2 ON r2.cliente_id = c.id
                {$joinPeriodo}
                " . ($cond !== [] ? 'WHERE ' . implode(' AND ', $cond) : '') . "
                ORDER BY {$orderBy}";

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($par);

        return $stmt->fetchAll();
    }

    /**
     * Ajustes manuales del periodo (quién, cuándo, cliente, cantidad y motivo).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function ajustes(string $desde, string $hasta, int $usuarioId): array
    {
        $par = ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];
        $extra = '';
        if ($usuarioId > 0) {
            $extra = ' AND m.usuario_id = :usuario';
            $par['usuario'] = $usuarioId;
        }

        $stmt = Database::conexion()->prepare(
            "SELECT m.creado_en, m.tipo, m.cantidad, m.motivo, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                    u.nombre AS usuario_nombre
             FROM lealtad_movimientos m
             JOIN clientes c ON c.id = m.cliente_id
             LEFT JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.tipo IN ('ajuste_positivo', 'ajuste_negativo') AND m.estado = 'activo'
               AND m.creado_en BETWEEN :desde AND :hasta{$extra}
             ORDER BY m.creado_en ASC, m.id ASC"
        );
        $stmt->execute($par);

        return $stmt->fetchAll();
    }

    /**
     * Acciones registradas en Auditoría en el periodo, excepto el registro normal de consumos.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function acciones(string $desde, string $hasta, int $usuarioId, string $accion): array
    {
        $par = ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];
        $extra = '';
        if ($usuarioId > 0) {
            $extra .= ' AND b.usuario_id = :usuario';
            $par['usuario'] = $usuarioId;
        }
        if (in_array($accion, ['crear', 'editar', 'eliminar', 'ajuste', 'canje'], true)) {
            $extra .= ' AND b.accion = :accion';
            $par['accion'] = $accion;
        }

        $stmt = Database::conexion()->prepare(
            "SELECT b.*, u.nombre AS usuario_nombre, c.nombre AS cliente_nombre
             FROM bitacora_auditoria b
             LEFT JOIN usuarios u ON u.id = b.usuario_id
             LEFT JOIN clientes c ON b.tabla_afectada IN ('lealtad_movimientos', 'clientes') AND c.id = b.registro_id
             WHERE b.accion <> 'consumo' AND b.accion <> 'login' AND b.creado_en BETWEEN :desde AND :hasta{$extra}
             ORDER BY b.creado_en DESC, b.id DESC"
        );
        $stmt->execute($par);

        return $stmt->fetchAll();
    }

    /**
     * Promociones con el estado calculado y los cupones por estado.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function promociones(string $hoy): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT pr.id, pr.nombre, pr.tipo, pr.valor, pr.etiqueta_personalizada, pr.fecha_inicio, pr.fecha_fin, pr.activo,
                    pr.combinable_con_lealtad,
                    COALESCE(SUM(cp.id IS NOT NULL), 0) AS reclamados,
                    COALESCE(SUM(cp.estado = 'canjeado'), 0) AS canjeados,
                    COALESCE(SUM(cp.estado = 'cancelado'), 0) AS cancelados,
                    COALESCE(SUM(cp.estado = 'vencido'), 0) AS vencidos
             FROM promociones pr LEFT JOIN cupones_promocion cp ON cp.promocion_id = pr.id
             GROUP BY pr.id
             ORDER BY pr.fecha_inicio DESC, pr.id DESC"
        );
        $stmt->execute();

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            if ((int) $f['activo'] === 0) {
                $estado = 'Inactiva';
            } elseif ($f['fecha_inicio'] > $hoy) {
                $estado = 'Próxima';
            } elseif ($f['fecha_fin'] < $hoy) {
                $estado = 'Vencida';
            } else {
                $estado = 'Vigente';
            }
            $f['estado_texto'] = $estado;
            $filas[] = $f;
        }

        return $filas;
    }

    /**
     * Productos por categoría.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function menu(string $filtro): array
    {
        $where = '';
        if ($filtro === 'sin_foto') {
            $where = "WHERE (p.imagen IS NULL OR p.imagen = '')";
        } elseif ($filtro === 'inactivos') {
            $where = 'WHERE p.activo = 0';
        }

        $stmt = Database::conexion()->prepare(
            "SELECT c.nombre AS categoria, c.activo AS categoria_activa, p.nombre, p.precio, p.precio_alt, p.etiqueta_precio_alt,
                    p.activo, p.es_destacado, p.es_mas_vendido, (p.imagen IS NOT NULL AND p.imagen <> '') AS tiene_foto
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             {$where}
             ORDER BY c.orden ASC, c.nombre ASC, p.orden ASC, p.nombre ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
