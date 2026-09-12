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
            'SELECT m.*, u.nombre AS usuario_nombre
             FROM lealtad_movimientos m
             JOIN usuarios u ON u.id = m.usuario_id
             WHERE m.cliente_id = :cliente_id
             ORDER BY m.creado_en DESC'
        );
        $stmt->execute(['cliente_id' => $clienteId]);

        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM lealtad_movimientos WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Inserta un movimiento. Debe llamarse siempre dentro de una
     * transacción abierta por el servicio (LealtadService), nunca de
     * forma aislada, para que el movimiento y el cambio de saldo/
     * recompensa asociado sean atómicos.
     *
     * @param string $tipo consumo|ajuste_positivo|ajuste_negativo|recompensa_generada|recompensa_canjeada|recompensa_cancelada
     */
    public function insertar(
        int $clienteId,
        string $tipo,
        int $cantidad,
        int $usuarioId,
        ?string $motivo = null,
        ?string $referencia = null
    ): int {
        Database::conexion()->prepare(
            'INSERT INTO lealtad_movimientos (cliente_id, tipo, cantidad, usuario_id, motivo, referencia)
             VALUES (:cliente_id, :tipo, :cantidad, :usuario_id, :motivo, :referencia)'
        )->execute([
            'cliente_id' => $clienteId,
            'tipo'       => $tipo,
            'cantidad'   => $cantidad,
            'usuario_id' => $usuarioId,
            'motivo'     => $motivo,
            'referencia' => $referencia,
        ]);

        return (int) Database::conexion()->lastInsertId();
    }
}
