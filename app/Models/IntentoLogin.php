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

    /**
     * Racha actual de intentos fallidos de un teléfono desde una IP: los fallidos seguidos desde
     * el último inicio de sesión correcto, cortada si entre dos fallidos pasaron más de
     * $reinicioSegundos (o si el último ya es más viejo que eso). Todo se calcula con la hora
     * de la base de datos, la misma que escribió `creado_en`.
     *
     * @return array{fallos:int,hace_segundos:int} hace_segundos = cuánto pasó desde el último fallido
     */
    public function rachaDeFallos(string $telefono, string $ip, int $reinicioSegundos): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT exitoso, UNIX_TIMESTAMP(creado_en) AS ts, UNIX_TIMESTAMP() AS ahora
             FROM intentos_login
             WHERE usuario_o_ip = :telefono AND ip = :ip
             ORDER BY id DESC LIMIT 500'
        );
        $stmt->execute(['telefono' => $telefono, 'ip' => $ip]);

        $fallos = 0;
        $ultimo = null;   // marca de tiempo del fallido más reciente
        $previo = null;
        $ahora = 0;
        foreach ($stmt->fetchAll() as $fila) {
            $ahora = (int) $fila['ahora'];
            $ts = (int) $fila['ts'];
            if ((int) $fila['exitoso'] === 1) {
                break;                                   // entró bien: la racha empieza de cero
            }
            if ($previo === null && $ahora - $ts > $reinicioSegundos) {
                break;                                   // el último fallido ya es muy viejo
            }
            if ($previo !== null && $previo - $ts > $reinicioSegundos) {
                break;                                   // hubo una pausa larga entre fallidos
            }
            $fallos++;
            $ultimo ??= $ts;
            $previo = $ts;
        }

        return ['fallos' => $fallos, 'hace_segundos' => $ultimo === null ? 0 : max(0, $ahora - $ultimo)];
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
