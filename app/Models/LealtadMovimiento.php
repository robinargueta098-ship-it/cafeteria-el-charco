<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class LealtadMovimiento
{
    /** @return array<int,array<string,mixed>> Historial de un cliente, más reciente primero. */
    public function historialPorCliente(int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT m.*, p.nombre AS producto_nombre, u.nombre AS usuario_nombre
             FROM lealtad_movimientos m
             LEFT JOIN productos p ON p.id = m.producto_canjeado_id
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.cliente_id = :cliente_id
             ORDER BY m.creado_en DESC'
        );
        $stmt->execute(['cliente_id' => $clienteId]);

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Todos los canjes (para el módulo Recompensas), más reciente primero. */
    public function historialDeCanjes(): array
    {
        return Database::conexion()->query(
            'SELECT m.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                    p.nombre AS producto_nombre, u.nombre AS usuario_nombre
             FROM lealtad_movimientos m
             JOIN clientes c ON c.id = m.cliente_id
             LEFT JOIN productos p ON p.id = m.producto_canjeado_id
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.tipo = "canje"
             ORDER BY m.creado_en DESC'
        )->fetchAll();
    }
}
