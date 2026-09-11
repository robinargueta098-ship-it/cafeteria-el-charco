<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\IntentoLogin;
use App\Models\Usuario;

/**
 * Autenticación del panel administrativo. Reglas de docs/arquitectura.md §7:
 * password_hash/password_verify, session_regenerate_id al iniciar sesión,
 * bloqueo temporal tras intentos fallidos (por cuenta y por IP).
 */
class Auth
{
    private const MAX_INTENTOS_CUENTA = 5;
    private const BLOQUEO_MINUTOS = 15;

    private const MAX_INTENTOS_IP = 20;
    private const VENTANA_IP_MINUTOS = 15;

    /**
     * Hash de un valor que nunca es una contraseña real. Se usa para que
     * password_verify() tarde lo mismo cuando el usuario no existe que
     * cuando sí existe, y así no se pueda distinguir uno de otro por el
     * tiempo de respuesta (enumeración de usuarios).
     */
    private const HASH_FICTICIO = '$2y$10$Ldk/pjo2TTJuHMgYMjS/feP6CrMWdTyM8VMXO9mIj6wag3v.7iOMW';

    public static function intentar(string $usuario, string $password): bool
    {
        $ip = self::ipCliente();
        $userAgent = self::agenteUsuario();
        $intentos = new IntentoLogin();

        // Límite por IP: evita probar contraseñas contra muchas cuentas
        // distintas desde el mismo origen (el bloqueo por cuenta de más
        // abajo no protege contra esto).
        if ($intentos->contarFallidosRecientesPorIp($ip, self::VENTANA_IP_MINUTOS) >= self::MAX_INTENTOS_IP) {
            Logger::error("Login bloqueado por exceso de intentos desde la IP {$ip}");
            return false;
        }

        $modelo = new Usuario();
        $registro = $modelo->buscarPorUsuario($usuario);

        $puedeIntentar = $registro && (int) $registro['activo'] && !self::estaBloqueado($registro);

        // Siempre se llama password_verify(), exista o no el usuario /
        // esté o no bloqueado, para que el tiempo de respuesta no lo
        // delate. El hash ficticio nunca puede coincidir con $password.
        $hashContraComparar = $puedeIntentar ? $registro['password_hash'] : self::HASH_FICTICIO;
        $valido = password_verify($password, $hashContraComparar) && $puedeIntentar;

        $intentos->registrar($usuario, $valido, $ip, $userAgent);

        if (!$valido) {
            if ($registro) {
                $modelo->registrarIntentoFallido((int) $registro['id'], self::MAX_INTENTOS_CUENTA, self::BLOQUEO_MINUTOS);
            }
            return false;
        }

        Session::regenerar();
        $_SESSION['usuario_id']     = (int) $registro['id'];
        $_SESSION['usuario_nombre'] = $registro['nombre'];
        $_SESSION['usuario_rol']    = (int) $registro['rol_id'];

        $modelo->registrarAccesoExitoso((int) $registro['id']);

        return true;
    }

    public static function verificado(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    /** @return array{id:int,nombre:string,rol_id:int}|null */
    public static function usuario(): ?array
    {
        if (!self::verificado()) {
            return null;
        }

        return [
            'id'     => (int) $_SESSION['usuario_id'],
            'nombre' => (string) $_SESSION['usuario_nombre'],
            'rol_id' => (int) $_SESSION['usuario_rol'],
        ];
    }

    public static function cerrarSesion(): void
    {
        Session::destruir();
    }

    private static function estaBloqueado(array $registro): bool
    {
        return !empty($registro['bloqueado_hasta']) && strtotime($registro['bloqueado_hasta']) > time();
    }

    private static function ipCliente(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');
    }

    private static function agenteUsuario(): ?string
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }

        return substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255);
    }
}
