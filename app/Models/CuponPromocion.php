<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Cupón de una promoción marcada "combinable con lealtad": el cliente lo
 * reclama desde su cuenta y el cajero lo canjea por código/QR, igual que
 * una recompensa de café gratis — pero es un concepto distinto (tabla
 * separada, docs/arquitectura.md §4.20): no hay cafés acumulados de por
 * medio, el "premio" es el que ya define la promoción (descuento, 2x1…).
 */
class CuponPromocion
{
    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM cupones_promocion WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Búsqueda exacta por código, sin bloqueo — para mostrar detalle antes de confirmar el canje. */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT cp.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                    p.nombre AS promocion_nombre, p.tipo AS promocion_tipo, p.valor AS promocion_valor,
                    p.fecha_fin AS promocion_fecha_fin
             FROM cupones_promocion cp
             JOIN clientes c ON c.id = cp.cliente_id
             JOIN promociones p ON p.id = cp.promocion_id
             WHERE cp.codigo = :codigo'
        );
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    /** Bloquea la fila (SELECT ... FOR UPDATE) dentro de una transacción ya abierta — para canjear sin condición de carrera. */
    public function buscarPorCodigoConBloqueo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM cupones_promocion WHERE codigo = :codigo FOR UPDATE');
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public function codigoExiste(string $codigo): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM cupones_promocion WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $codigo]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** El cupón (de cualquier estado) que este cliente ya reclamó de esta promoción, si existe. */
    public function buscarDeClientePorPromocion(int $clienteId, int $promocionId): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM cupones_promocion WHERE cliente_id = :cliente_id AND promocion_id = :promocion_id'
        );
        $stmt->execute(['cliente_id' => $clienteId, 'promocion_id' => $promocionId]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO cupones_promocion (codigo, cliente_id, promocion_id)
             VALUES (:codigo, :cliente_id, :promocion_id)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    /**
     * Marca el cupón como canjeado. El `AND estado = 'disponible'` hace la
     * operación atómica: aunque el llamador se equivocara y no tuviera el
     * candado, un cupón que ya no está disponible NO se vuelve a canjear.
     */
    public function marcarCanjeado(int $id, int $usuarioId): bool
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE cupones_promocion
             SET estado = 'canjeado', usuario_canjeo_id = :usuario_id, fecha_canje = NOW()
             WHERE id = :id AND estado = 'disponible'"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'id' => $id]);

        return $stmt->rowCount() === 1;
    }

    public function marcarVencido(int $id): void
    {
        Database::conexion()->prepare("UPDATE cupones_promocion SET estado = 'vencido' WHERE id = :id")
            ->execute(['id' => $id]);
    }

    /** Solo Administrador (se exige en el controlador) — cancela sin devolver nada: el cupón no está atado a cafés acumulados. */
    public function marcarCancelado(int $id, int $usuarioId, string $motivo): void
    {
        Database::conexion()->prepare(
            "UPDATE cupones_promocion
             SET estado = 'cancelado', usuario_cancelacion_id = :usuario_id,
                 fecha_cancelacion = NOW(), motivo_cancelacion = :motivo
             WHERE id = :id"
        )->execute(['usuario_id' => $usuarioId, 'motivo' => $motivo, 'id' => $id]);
    }

    /** @return array<int,array<string,mixed>> Historial para el panel (Administrador), más recientes primero. */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT cp.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                    p.nombre AS promocion_nombre, u.nombre AS usuario_canjeo_nombre
             FROM cupones_promocion cp
             JOIN clientes c ON c.id = cp.cliente_id
             JOIN promociones p ON p.id = cp.promocion_id
             LEFT JOIN usuarios u ON u.id = cp.usuario_canjeo_id
             ORDER BY cp.fecha_reclamo DESC
             LIMIT 200'
        )->fetchAll();
    }

    public function contarPorPromocion(int $promocionId): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM cupones_promocion WHERE promocion_id = :id');
        $stmt->execute(['id' => $promocionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Código de 8 caracteres, garantizado que en este momento no existe —
     * reutiliza el mismo generador aleatorio que las recompensas
     * (Recompensa::nuevoCodigoAleatorio(), mismo alfabeto sin ambigüedades),
     * solo que la comprobación de unicidad es contra esta tabla, la suya
     * propia. Ver crearCuponConCodigoUnico() en CuponPromocionService para
     * la garantía real (UNIQUE + reintento ante colisión).
     */
    public function generarCodigoUnico(): string
    {
        do {
            $codigo = Recompensa::nuevoCodigoAleatorio();
        } while ($this->codigoExiste($codigo));

        return $codigo;
    }
}
