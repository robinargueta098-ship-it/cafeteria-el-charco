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
    /**
     * El usuario principal del negocio (el primero que se creó). Está protegido: nadie puede desactivarlo, quitarle el
     * rol de Administrador ni cambiarle el nombre de usuario, ni desde el panel ni con una petición manipulada.
     * (El panel tampoco tiene ninguna opción para eliminar usuarios.)
     */
    public const ID_PRINCIPAL = 1;

    public static function esPrincipal(int $id): bool
    {
        return $id === self::ID_PRINCIPAL;
    }

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
    /**
     * Cuántos registros con historial real dependen de este usuario: movimientos de lealtad que registró y
     * recompensas o cupones que canjeó o canceló. Si tiene alguno NO se debe eliminar (se perdería quién hizo cada
     * operación, y la base de datos lo impide): se desactiva.
     */
    public function contarHistorial(int $id): int
    {
        $pdo = Database::conexion();
        $total = 0;
        foreach ([
            'SELECT COUNT(*) FROM lealtad_movimientos WHERE usuario_id = :id',
            'SELECT COUNT(*) FROM recompensas WHERE usuario_canjeo_id = :id OR usuario_cancelacion_id = :id2',
            'SELECT COUNT(*) FROM cupones_promocion WHERE usuario_canjeo_id = :id OR usuario_cancelacion_id = :id2',
        ] as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(str_contains($sql, ':id2') ? ['id' => $id, 'id2' => $id] : ['id' => $id]);
            $total += (int) $stmt->fetchColumn();
        }

        return $total;
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM usuarios WHERE id = :id')->execute(['id' => $id]);
    }

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
