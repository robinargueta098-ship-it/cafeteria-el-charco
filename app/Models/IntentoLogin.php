<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Registro de intentos de login (tabla `intentos_login`, definida en la
 * Fase 2 pero sin usar hasta ahora). Sirve para trazabilidad y para el
 * límite de intentos por IP (independiente del bloqueo por cuenta de
 * Auth::intentar()), que protege contra fuerza bruta distribuida entre
 * varios usuarios desde una misma IP.
 */
class IntentoLogin
{
    public function registrar(string $usuarioOIp, bool $exitoso, string $ip, ?string $userAgent): void
    {
        Database::conexion()->prepare(
            'INSERT INTO intentos_login (usuario_o_ip, exitoso, ip, user_agent)
             VALUES (:usuario_o_ip, :exitoso, :ip, :user_agent)'
        )->execute([
            'usuario_o_ip' => $usuarioOIp,
            'exitoso'      => $exitoso ? 1 : 0,
            'ip'           => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    public function contarFallidosRecientesPorIp(string $ip, int $minutos): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM intentos_login
             WHERE ip = :ip AND exitoso = 0
               AND creado_en >= DATE_SUB(NOW(), INTERVAL :minutos MINUTE)'
        );
        $stmt->execute(['ip' => $ip, 'minutos' => $minutos]);

        return (int) $stmt->fetchColumn();
    }
}
