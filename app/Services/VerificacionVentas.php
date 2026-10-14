<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Revisión de cuadre de los pedidos: busca datos que no concuerdan entre sí. La usan el script bin/verificar_ventas.php
 * (consola) y Reportes → «Verificación de datos» (solo Administrador). Solo lectura.
 * Compatible con PHP 7.4.
 */
class VerificacionVentas
{
    private const MUESTRA = 50;   // máximo de pedidos que se listan por cada revisión

    /**
     * @return array{pedidos:int,revisiones:array<string,int>,hallazgos:array<int,array{revision:string,detalle:string}>}
     */
    public static function ejecutar(): array
    {
        $pdo = Database::conexion();

        $revisiones = [
            'Pedidos cuyo total ≠ subtotal − descuento' =>
                'SELECT p.codigo AS ref, CONCAT("total=", p.total_centavos, " subtotal=", p.subtotal_centavos, " descuento=", p.descuento_centavos) AS dato
                 FROM pedidos p WHERE p.total_centavos + p.descuento_centavos <> p.subtotal_centavos',

            'Pedidos cuyo subtotal ≠ suma de sus ítems' =>
                'SELECT p.codigo AS ref, CONCAT("subtotal=", p.subtotal_centavos, " suma_items=", COALESCE(s.suma, 0)) AS dato
                 FROM pedidos p LEFT JOIN (SELECT pedido_id, SUM(subtotal_centavos) AS suma FROM pedido_items GROUP BY pedido_id) s ON s.pedido_id = p.id
                 WHERE p.subtotal_centavos <> COALESCE(s.suma, 0) AND EXISTS (SELECT 1 FROM pedido_items i WHERE i.pedido_id = p.id)',

            'Ítems cuyo subtotal ≠ precio × cantidad' =>
                'SELECT p.codigo AS ref, CONCAT(i.nombre_snapshot, ": ", i.precio_unitario_centavos, " x ", i.cantidad, " <> ", i.subtotal_centavos) AS dato
                 FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id
                 WHERE i.subtotal_centavos <> i.precio_unitario_centavos * i.cantidad',

            'Pedidos sin ítems' =>
                'SELECT p.codigo AS ref, "sin líneas" AS dato FROM pedidos p
                 WHERE NOT EXISTS (SELECT 1 FROM pedido_items i WHERE i.pedido_id = p.id)',

            'Pedidos entregados sin fecha de entrega' =>
                "SELECT p.codigo AS ref, \"estado=entregado, entregado_en vacío\" AS dato FROM pedidos p
                 WHERE p.estado = 'entregado' AND p.entregado_en IS NULL",

            'Fecha de entrega en pedidos que nunca se entregaron' =>
                "SELECT p.codigo AS ref, CONCAT(\"estado=\", p.estado, \" entregado_en=\", p.entregado_en) AS dato FROM pedidos p
                 WHERE p.entregado_en IS NOT NULL AND p.estado NOT IN ('entregado', 'anulado')",

            'Estado del pedido ≠ último estado del historial' =>
                'SELECT p.codigo AS ref, CONCAT("pedido=", p.estado, " historial=", COALESCE(h.estado_nuevo, "(sin historial)")) AS dato
                 FROM pedidos p
                 LEFT JOIN pedido_historial h ON h.id = (SELECT MAX(h2.id) FROM pedido_historial h2 WHERE h2.pedido_id = p.id)
                 WHERE h.id IS NULL OR h.estado_nuevo <> p.estado',

            'Historial con saltos de estado no permitidos' =>
                "SELECT p.codigo AS ref, CONCAT(h.estado_anterior, \" -> \", h.estado_nuevo) AS dato
                 FROM pedido_historial h JOIN pedidos p ON p.id = h.pedido_id
                 WHERE h.estado_anterior IS NOT NULL AND NOT (
                       (h.estado_anterior = 'enviado' AND h.estado_nuevo IN ('confirmado', 'cancelado'))
                    OR (h.estado_anterior = 'confirmado' AND h.estado_nuevo IN ('entregado', 'cancelado'))
                    OR (h.estado_anterior = 'entregado' AND h.estado_nuevo = 'anulado'))",

            'Ítems con cantidad o precio fuera de rango' =>
                'SELECT p.codigo AS ref, CONCAT(i.nombre_snapshot, ": cantidad=", i.cantidad, " precio=", i.precio_unitario_centavos) AS dato
                 FROM pedido_items i JOIN pedidos p ON p.id = i.pedido_id
                 WHERE i.cantidad < 1 OR i.cantidad > 20 OR (i.precio_unitario_centavos = 0 AND i.es_cortesia = 0)
                    OR (i.es_cortesia = 1 AND i.precio_unitario_centavos <> 0)',

            'Pedidos con total fuera de rango' =>
                'SELECT p.codigo AS ref, CONCAT("total=", p.total_centavos) AS dato FROM pedidos p
                 WHERE p.total_centavos <= 0 OR p.total_centavos > 500000',
        ];

        $salida = ['pedidos' => (int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn(), 'revisiones' => [], 'hallazgos' => []];

        foreach ($revisiones as $nombre => $sql) {
            $filas = $pdo->query($sql)->fetchAll();
            $salida['revisiones'][$nombre] = count($filas);
            foreach (array_slice($filas, 0, self::MUESTRA) as $f) {
                $salida['hallazgos'][] = ['revision' => $nombre, 'detalle' => 'Pedido #' . $f['ref'] . ' — ' . $f['dato']];
            }
        }

        return $salida;
    }
}
