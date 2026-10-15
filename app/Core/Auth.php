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
    /** Segundos sin actividad en el panel antes de pedir iniciar sesión de nuevo. */
    private const INACTIVIDAD_ADMIN = 7200;       // Administrador: 2 horas
    private const INACTIVIDAD_PERSONAL = 28800;   // Cajero y Repartidor: 8 horas (todo el turno)

    private const MAX_INTENTOS_IP = 20;
    private const VENTANA_IP_MINUTOS = 15;

    /**
     * Hash de un valor que nunca es una contraseña real. Se usa para que
     * password_verify() tarde lo mismo cuando el usuario no existe que
     * cuando sí existe, y así no se pueda distinguir uno de otro por el
     * tiempo de respuesta (enumeración de usuarios).
     */
    private const HASH_FICTICIO = '$2y$10$Ldk/pjo2TTJuHMgYMjS/feP6CrMWdTyM8VMXO9mIj6wag3v.7iOMW';

    /** Motivo del último intento fallido ('contrasena', 'bloqueado', 'inactivo', 'ip', 'desconocido') y la cuenta, si existe. */
    public static $ultimoMotivo = null;
    public static $ultimoUsuarioId = null;

    public static function intentar(string $usuario, string $password): bool
    {
        self::$ultimoMotivo = null;
        self::$ultimoUsuarioId = null;

        $ip = self::ipCliente();
        $userAgent = self::agenteUsuario();
        $intentos = new IntentoLogin();

        // Límite por IP: evita probar contraseñas contra muchas cuentas
        // distintas desde el mismo origen (el bloqueo por cuenta de más
        // abajo no protege contra esto).
        if ($intentos->contarFallidosRecientesPorIp($ip, self::VENTANA_IP_MINUTOS) >= self::MAX_INTENTOS_IP) {
            Logger::error("Login bloqueado por exceso de intentos desde la IP {$ip}");
            self::$ultimoMotivo = 'ip';
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
            if (!$registro) {
                self::$ultimoMotivo = 'desconocido';
            } else {
                self::$ultimoUsuarioId = (int) $registro['id'];
                self::$ultimoMotivo = !(int) $registro['activo'] ? 'inactivo' : (self::estaBloqueado($registro) ? 'bloqueado' : 'contrasena');
            }
            if ($registro) {
                $modelo->registrarIntentoFallido((int) $registro['id'], self::MAX_INTENTOS_CUENTA, self::BLOQUEO_MINUTOS);
            }
            return false;
        }

        Session::regenerar();
        // Token CSRF nuevo para la sesión ya autenticada: el del formulario de
        // login (anónimo) no debe seguir valiendo dentro del panel.
        unset($_SESSION['csrf_token']);
        $_SESSION['usuario_id']     = (int) $registro['id'];
        $_SESSION['usuario_nombre'] = $registro['nombre'];
        $_SESSION['usuario_rol']    = (int) $registro['rol_id'];
        $_SESSION['ultima_actividad'] = time();

        $modelo->registrarAccesoExitoso((int) $registro['id']);

        return true;
    }

    /**
     * Hay sesión de panel Y el usuario sigue existiendo y activo. Se consulta
     * la base una vez por petición: si a alguien lo desactivan o lo borran
     * mientras tiene la sesión abierta, pierde el acceso de inmediato (antes
     * seguía entrando hasta que cerrara sesión). El rol también se toma de la
     * base, así un cambio de rol se aplica sin volver a iniciar sesión.
     */
    public static function verificado(): bool
    {
        if (empty($_SESSION['usuario_id'])) {
            return false;
        }

        static $revisadoPara = null;
        if ($revisadoPara === $_SESSION['usuario_id']) {
            return true;
        }

        // Vencimiento por inactividad, según el rol que tenía al iniciar sesión (Administrador 2 h; Cajero y
        // Repartidor 8 h: trabajan todo el turno). Cada petición del panel reinicia el contador.
        $ahora = time();
        $limite = ((int) ($_SESSION['usuario_rol'] ?? 0)) === 1 ? self::INACTIVIDAD_ADMIN : self::INACTIVIDAD_PERSONAL;
        if (isset($_SESSION['ultima_actividad']) && $ahora - (int) $_SESSION['ultima_actividad'] > $limite) {
            Session::destruir();
            Session::iniciar();
            return false;
        }
        $_SESSION['ultima_actividad'] = $ahora;

        $registro = (new Usuario())->buscarPorId((int) $_SESSION['usuario_id']);

        if (!$registro || !(int) $registro['activo']) {
            // Sesión huérfana: se descarta y se abre una vacía y limpia para
            // que la pantalla de login pueda generar su token CSRF.
            Session::destruir();
            Session::iniciar();
            return false;
        }

        $_SESSION['usuario_nombre'] = $registro['nombre'];
        $_SESSION['usuario_rol']    = (int) $registro['rol_id'];
        $revisadoPara = $_SESSION['usuario_id'];

        return true;
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
