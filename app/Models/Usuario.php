<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Usuarios del panel administrativo (no confundir con `clientes`,
 * que son los clientes del programa de lealtad).
 */
class Usuario
{
    public function buscarPorUsuario(string $usuario): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id, rol_id, nombre, usuario, password_hash, activo, bloqueado_hasta
             FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuario]);

        $registro = $stmt->fetch();
        return $registro ?: null;
    }

    public function registrarIntentoFallido(int $id, int $maxIntentos, int $minutosBloqueo): void
    {
        $pdo = Database::conexion();

        $pdo->prepare(
            'UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id'
        )->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT intentos_fallidos FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $intentos = (int) $stmt->fetchColumn();

        if ($intentos >= $maxIntentos) {
            $pdo->prepare(
                'UPDATE usuarios
                 SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :minutos MINUTE)
                 WHERE id = :id'
            )->execute(['minutos' => $minutosBloqueo, 'id' => $id]);
        }
    }

    public function registrarAccesoExitoso(int $id): void
    {
        Database::conexion()->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
             WHERE id = :id'
        )->execute(['id' => $id]);
    }

    // --- CRUD del módulo Usuarios (panel administrativo) ---

    /** @return array<int,array<string,mixed>> */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.nombre ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(string $nombre, string $usuario, string $passwordHash, int $rolId, bool $activo): int
    {
        Database::conexion()->prepare(
            'INSERT INTO usuarios (nombre, usuario, password_hash, rol_id, activo)
             VALUES (:nombre, :usuario, :password_hash, :rol_id, :activo)'
        )->execute([
            'nombre' => $nombre, 'usuario' => $usuario, 'password_hash' => $passwordHash,
            'rol_id' => $rolId, 'activo' => $activo ? 1 : 0,
        ]);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizarDatos(int $id, string $nombre, string $usuario, int $rolId, bool $activo): void
    {
        Database::conexion()->prepare(
            'UPDATE usuarios SET nombre = :nombre, usuario = :usuario, rol_id = :rol_id, activo = :activo WHERE id = :id'
        )->execute([
            'nombre' => $nombre, 'usuario' => $usuario, 'rol_id' => $rolId, 'activo' => $activo ? 1 : 0, 'id' => $id,
        ]);
    }

    public function actualizarPassword(int $id, string $passwordHash): void
    {
        Database::conexion()->prepare(
            'UPDATE usuarios SET password_hash = :hash, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id'
        )->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Cuántos usuarios Administrador (rol_id=1) activos hay, sin contar a $excluirId. */
    public function contarAdministradoresActivos(?int $excluirId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND activo = 1';
        $parametros = [];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros['excluir_id'] = $excluirId;
        }

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }
}
