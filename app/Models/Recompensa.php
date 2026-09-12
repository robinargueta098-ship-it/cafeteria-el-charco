<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Una fila por cada café gratis generado al cruzar el umbral de
 * lealtad. El código es lo único que identifica una recompensa para
 * canjearla — nunca se busca ni se canjea por cliente_id directo desde
 * la pantalla de canje (docs/arquitectura.md: canje por código).
 */
class Recompensa
{
    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM recompensas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Búsqueda exacta por código, sin bloqueo — para mostrar detalle antes de confirmar el canje. */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM recompensas r JOIN clientes c ON c.id = r.cliente_id
             WHERE r.codigo = :codigo'
        );
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public function codigoExiste(string $codigo): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM recompensas WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $codigo]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int,array<string,mixed>> Recompensas de un cliente, más recientes primero. */
    public function listarPorCliente(int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, p.nombre AS producto_nombre
             FROM recompensas r
             LEFT JOIN productos p ON p.id = r.producto_canjeado_id
             WHERE r.cliente_id = :cliente_id
             ORDER BY r.fecha_generada DESC'
        );
        $stmt->execute(['cliente_id' => $clienteId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{estado?:string} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function listarTodas(array $filtros = []): array
    {
        $sql = 'SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                       p.nombre AS producto_nombre, u.nombre AS usuario_canjeo_nombre
                FROM recompensas r
                JOIN clientes c ON c.id = r.cliente_id
                LEFT JOIN productos p ON p.id = r.producto_canjeado_id
                LEFT JOIN usuarios u ON u.id = r.usuario_canjeo_id';
        $parametros = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' WHERE r.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY r.fecha_generada DESC LIMIT 200';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO recompensas (cliente_id, codigo, movimiento_generador_id, fecha_vencimiento)
             VALUES (:cliente_id, :codigo, :movimiento_generador_id, :fecha_vencimiento)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function marcarCanjeada(int $id, int $productoId, int $usuarioId, int $movimientoCanjeId): void
    {
        Database::conexion()->prepare(
            'UPDATE recompensas
             SET estado = "canjeada", producto_canjeado_id = :producto_id,
                 usuario_canjeo_id = :usuario_id, fecha_canjeada = NOW(),
                 movimiento_canje_id = :movimiento_id
             WHERE id = :id'
        )->execute([
            'producto_id'   => $productoId,
            'usuario_id'    => $usuarioId,
            'movimiento_id' => $movimientoCanjeId,
            'id'            => $id,
        ]);
    }

    public function marcarVencida(int $id): void
    {
        Database::conexion()->prepare("UPDATE recompensas SET estado = 'vencida' WHERE id = :id")
            ->execute(['id' => $id]);
    }

    public function marcarCancelada(int $id, int $usuarioId, string $motivo, int $movimientoCancelacionId): void
    {
        Database::conexion()->prepare(
            'UPDATE recompensas
             SET estado = "cancelada", usuario_cancelacion_id = :usuario_id,
                 fecha_cancelacion = NOW(), motivo_cancelacion = :motivo,
                 movimiento_cancelacion_id = :movimiento_id
             WHERE id = :id'
        )->execute([
            'usuario_id'    => $usuarioId,
            'motivo'        => $motivo,
            'movimiento_id' => $movimientoCancelacionId,
            'id'            => $id,
        ]);
    }

    /**
     * Bloquea la fila (SELECT ... FOR UPDATE) dentro de una transacción
     * ya abierta por el llamador — para canjear/cancelar sin condición
     * de carrera. Debe usarse siempre dentro de una transacción.
     */
    public function buscarPorCodigoConBloqueo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM recompensas WHERE codigo = :codigo FOR UPDATE'
        );
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    /** Igual que buscarPorCodigoConBloqueo pero por ID (para cancelar). Usar dentro de una transacción. */
    public function buscarPorIdConBloqueo(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM recompensas WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function generarCodigoUnico(): string
    {
        // Alfabeto sin caracteres ambiguos (0/O, 1/I/L) para que el
        // cliente lo pueda leer y el cajero teclear sin confusiones.
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $codigo = '';
            for ($i = 0; $i < 8; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigo = substr($codigo, 0, 4) . '-' . substr($codigo, 4);
        } while ($this->codigoExiste($codigo));

        return $codigo;
    }
}
