<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTime;

/**
 * ÚNICO lugar donde se define qué es una VENTA. Todos los reportes, pantallas y exportaciones usan esta clase:
 * está prohibido repetir esta lógica en otro sitio.
 *
 *   Una venta es un pedido en estado ENTREGADO, contabilizada en la fecha de `entregado_en` (hora de Guatemala).
 *   El ingreso es `total_centavos`. Anulados, cancelados, enviados y confirmados NO son ventas.
 *   Las cortesías (`es_cortesia = 1`, precio 0) no suman ingreso.
 *
 * Dinero en centavos enteros. Rangos inclusivos: 'Y-m-d 00:00:00' a 'Y-m-d 23:59:59'. Sentencias preparadas.
 * Compatible con PHP 7.4.
 */
class VentasService
{
    /** Condición SQL de "es una venta del periodo" para la tabla `pedidos` con alias p. Parámetros: :desde, :hasta. */
    private const CONDICION_VENTA = "p.estado = 'entregado' AND p.entregado_en BETWEEN :desde AND :hasta";

    /** @return array<string,string> */
    private static function rango(string $desde, string $hasta): array
    {
        return ['desde' => $desde . ' 00:00:00', 'hasta' => $hasta . ' 23:59:59'];
    }

    /**
     * @return array<string,int> ventas_centavos, pedidos, ticket_promedio_centavos, descuentos_centavos,
     *         pendientes, cancelados, anulados, creados, tasa_cancelacion_diezmilesimas (porcentaje × 100)
     */
    public static function resumen(string $desde, string $hasta): array
    {
        $pdo = Database::conexion();
        $r = self::rango($desde, $hasta);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS pedidos, COALESCE(SUM(p.total_centavos), 0) AS ventas, COALESCE(SUM(p.descuento_centavos), 0) AS descuentos
             FROM pedidos p WHERE ' . self::CONDICION_VENTA
        );
        $stmt->execute($r);
        $v = $stmt->fetch();
        $pedidos = (int) $v['pedidos'];
        $ventas = (int) $v['ventas'];

        // Pedidos "creados en el periodo" por estado actual (cohorte por fecha de creación).
        $stmt = $pdo->prepare(
            "SELECT estado, COUNT(*) AS n FROM pedidos p WHERE p.creado_en BETWEEN :desde AND :hasta GROUP BY estado"
        );
        $stmt->execute($r);
        $porEstado = ['enviado' => 0, 'confirmado' => 0, 'entregado' => 0, 'cancelado' => 0, 'anulado' => 0];
        foreach ($stmt->fetchAll() as $fila) {
            $porEstado[$fila['estado']] = (int) $fila['n'];
        }
        $creados = array_sum($porEstado);

        return [
            'ventas_centavos'          => $ventas,
            'pedidos'                  => $pedidos,
            // Ticket promedio = ventas / pedidos entregados, redondeado half-up al centavo con enteros; 0 si no hay.
            'ticket_promedio_centavos' => $pedidos > 0 ? intdiv(2 * $ventas + $pedidos, 2 * $pedidos) : 0,
            'descuentos_centavos'      => (int) $v['descuentos'],
            'pendientes'               => $porEstado['enviado'] + $porEstado['confirmado'],
            'cancelados'               => $porEstado['cancelado'],
            'anulados'                 => $porEstado['anulado'],
            'creados'                  => $creados,
            // Tasa de cancelación = cancelados / pedidos creados en el periodo (en diezmilésimas de %: 1250 = 12.50 %).
            'tasa_cancelacion'         => $creados > 0 ? intdiv(2 * $porEstado['cancelado'] * 10000 + $creados, 2 * $creados) : 0,
        ];
    }

    /**
     * Ventas por día, incluyendo los días sin ventas (en 0).
     *
     * @return array<int,array{fecha:string,pedidos:int,ventas_centavos:int}>
     */
    public static function porDia(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT DATE(p.entregado_en) AS dia, COUNT(*) AS pedidos, SUM(p.total_centavos) AS ventas
             FROM pedidos p WHERE ' . self::CONDICION_VENTA . ' GROUP BY DATE(p.entregado_en)'
        );
        $stmt->execute(self::rango($desde, $hasta));
        $porDia = [];
        foreach ($stmt->fetchAll() as $f) {
            $porDia[$f['dia']] = ['pedidos' => (int) $f['pedidos'], 'ventas' => (int) $f['ventas']];
        }

        $salida = [];
        $fin = new DateTime($hasta);
        $fin->modify('+1 day');
        foreach (new DatePeriod(new DateTime($desde), new DateInterval('P1D'), $fin) as $d) {
            $clave = $d->format('Y-m-d');
            $salida[] = [
                'fecha'           => $clave,
                'pedidos'         => isset($porDia[$clave]) ? $porDia[$clave]['pedidos'] : 0,
                'ventas_centavos' => isset($porDia[$clave]) ? $porDia[$clave]['ventas'] : 0,
            ];
        }

        return $salida;
    }

    /**
     * Productos más vendidos (por el nombre guardado en el pedido). El ingreso excluye las cortesías.
     *
     * @return array<int,array{producto:string,categoria:string,cantidad:int,ingreso_centavos:int}>
     */
    public static function masVendidos(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT i.nombre_snapshot AS producto, i.categoria_snapshot AS categoria,
                    SUM(i.cantidad) AS cantidad,
                    SUM(CASE WHEN i.es_cortesia = 0 THEN i.subtotal_centavos ELSE 0 END) AS ingreso
             FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id
             WHERE ' . self::CONDICION_VENTA . '
             GROUP BY i.nombre_snapshot, i.categoria_snapshot
             ORDER BY cantidad DESC, ingreso DESC, producto ASC'
        );
        $stmt->execute(self::rango($desde, $hasta));

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = ['producto' => $f['producto'], 'categoria' => $f['categoria'], 'cantidad' => (int) $f['cantidad'], 'ingreso_centavos' => (int) $f['ingreso']];
        }

        return $filas;
    }

    /** @return array<int,array{categoria:string,cantidad:int,ingreso_centavos:int}> */
    public static function porCategoria(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT i.categoria_snapshot AS categoria, SUM(i.cantidad) AS cantidad,
                    SUM(CASE WHEN i.es_cortesia = 0 THEN i.subtotal_centavos ELSE 0 END) AS ingreso
             FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id
             WHERE ' . self::CONDICION_VENTA . '
             GROUP BY i.categoria_snapshot
             ORDER BY ingreso DESC, categoria ASC'
        );
        $stmt->execute(self::rango($desde, $hasta));

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = ['categoria' => $f['categoria'], 'cantidad' => (int) $f['cantidad'], 'ingreso_centavos' => (int) $f['ingreso']];
        }

        return $filas;
    }

    /** @return array<int,array{forma_pago:string,pedidos:int,ventas_centavos:int}> */
    public static function porFormaPago(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.forma_pago, COUNT(*) AS pedidos, SUM(p.total_centavos) AS ventas
             FROM pedidos p WHERE ' . self::CONDICION_VENTA . '
             GROUP BY p.forma_pago ORDER BY ventas DESC, p.forma_pago ASC'
        );
        $stmt->execute(self::rango($desde, $hasta));

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = ['forma_pago' => $f['forma_pago'], 'pedidos' => (int) $f['pedidos'], 'ventas_centavos' => (int) $f['ventas']];
        }

        return $filas;
    }

    /**
     * Horas pico (hora de entrega, 0-23). Solo las horas con ventas.
     *
     * @return array<int,array{hora:int,pedidos:int,ventas_centavos:int}>
     */
    public static function horasPico(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT HOUR(p.entregado_en) AS hora, COUNT(*) AS pedidos, SUM(p.total_centavos) AS ventas
             FROM pedidos p WHERE ' . self::CONDICION_VENTA . '
             GROUP BY HOUR(p.entregado_en) ORDER BY pedidos DESC, hora ASC'
        );
        $stmt->execute(self::rango($desde, $hasta));

        $filas = [];
        foreach ($stmt->fetchAll() as $f) {
            $filas[] = ['hora' => (int) $f['hora'], 'pedidos' => (int) $f['pedidos'], 'ventas_centavos' => (int) $f['ventas']];
        }

        return $filas;
    }

    /**
     * Pedidos que son venta en el periodo (para el listado/CSV de ventas).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pedidosVendidos(string $desde, string $hasta): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.id, p.codigo, p.entregado_en, p.creado_en, p.cliente_nombre, p.cliente_telefono, p.forma_pago,
                    p.subtotal_centavos, p.descuento_centavos, p.total_centavos
             FROM pedidos p WHERE ' . self::CONDICION_VENTA . '
             ORDER BY p.entregado_en ASC, p.id ASC'
        );
        $stmt->execute(self::rango($desde, $hasta));

        return $stmt->fetchAll();
    }
}
