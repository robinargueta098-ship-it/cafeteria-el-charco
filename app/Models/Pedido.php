<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Consultas de pedidos para el panel. Dinero en centavos enteros. Sentencias preparadas siempre.
 * Sintaxis compatible con PHP 7.4.
 */
class Pedido
{
    public const POR_PAGINA = 50;

    public const ESTADOS = ['pendiente', 'enviado', 'confirmado', 'entregado', 'cancelado', 'anulado'];

    public const ETIQUETAS_ESTADO = [
        'pendiente'  => 'Sin enviar',
        'enviado'    => 'Nuevo',
        'confirmado' => 'Confirmado',
        'entregado'  => 'Entregado',
        'cancelado'  => 'Cancelado',
        'anulado'    => 'Anulado',
    ];

    public const ETIQUETAS_PAGO = [
        'contra_entrega' => 'Pago contra entrega',
        'transferencia'  => 'Transferencia',
        'tarjeta_fisica' => 'Tarjeta física',
    ];

    /**
     * @param array{estado?:string,desde?:string,hasta?:string,q?:string} $filtros desde/hasta: Y-m-d (inclusivos, por fecha de creación)
     * @return array{filas:array<int,array<string,mixed>>,total:int,pagina:int,totalPaginas:int}
     */
    public function listar(array $filtros, int $pagina): array
    {
        $condiciones = [];
        $parametros = [];

        if (!empty($filtros['estado']) && in_array($filtros['estado'], self::ESTADOS, true)) {
            $condiciones[] = 'p.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        } else {
            // «Sin confirmar» (el cliente no confirmó que envió el mensaje) no se mezcla con los pedidos reales.
            $condiciones[] = "p.estado <> 'pendiente'";
        }
        if (!empty($filtros['desde'])) {
            $condiciones[] = 'p.creado_en >= :desde';
            $parametros['desde'] = $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $condiciones[] = 'p.creado_en <= :hasta';
            $parametros['hasta'] = $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['q'])) {
            // Código (exacto, sin importar mayúsculas) o teléfono / nombre (parcial). Dos parámetros distintos:
            // con ATTR_EMULATE_PREPARES=false no se puede repetir un mismo nombre.
            $comodin = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filtros['q']) . '%';
            $condiciones[] = "(p.codigo = :codigo OR p.cliente_telefono LIKE :telefono ESCAPE '\\\\' OR p.cliente_nombre LIKE :nombre ESCAPE '\\\\')";
            $parametros['codigo'] = strtoupper($filtros['q']);
            $parametros['telefono'] = $comodin;
            $parametros['nombre'] = $comodin;
        }

        $where = $condiciones !== [] ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $stmt = Database::conexion()->prepare("SELECT COUNT(*) FROM pedidos p {$where}");
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        $totalPaginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = max(1, min($pagina, $totalPaginas));
        $offset = ($pagina - 1) * self::POR_PAGINA;

        $stmt = Database::conexion()->prepare(
            "SELECT p.*, (SELECT COUNT(*) FROM pedido_items i WHERE i.pedido_id = p.id) AS lineas
             FROM pedidos p
             {$where}
             ORDER BY (p.estado = 'enviado') DESC, p.creado_en DESC, p.id DESC
             LIMIT " . self::POR_PAGINA . " OFFSET {$offset}"
        );
        $stmt->execute($parametros);

        return ['filas' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'totalPaginas' => $totalPaginas];
    }

    /** @return array<string,mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.*, c.nombre AS cliente_cuenta_nombre, u.nombre AS actualizado_por_nombre
             FROM pedidos p
             LEFT JOIN clientes c ON c.id = p.cliente_id
             LEFT JOIN usuarios u ON u.id = p.actualizado_por
             WHERE p.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $pedidoId): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM pedido_items WHERE pedido_id = :id ORDER BY id');
        $stmt->execute(['id' => $pedidoId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function historial(int $pedidoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT h.*, u.nombre AS usuario_nombre
             FROM pedido_historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.pedido_id = :id
             ORDER BY h.fecha ASC, h.id ASC'
        );
        $stmt->execute(['id' => $pedidoId]);

        return $stmt->fetchAll();
    }

    /** Pedidos que el cliente armó pero no confirmó haber enviado por WhatsApp. */
    public function contarSinConfirmarPorElCliente(): int
    {
        return (int) Database::conexion()->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
    }

    /** Cuántos pedidos esperan atención (enviados, todavía sin confirmar). */
    public function contarPendientesDeConfirmar(): int
    {
        return (int) Database::conexion()->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'enviado'")->fetchColumn();
    }
}
