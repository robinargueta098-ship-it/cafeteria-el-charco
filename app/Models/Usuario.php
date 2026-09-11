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
}
