<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Cliente;
use App\Models\IntentoLogin;

/**
 * Sesión de cliente (teléfono + PIN de 4 dígitos) — completamente
 * separada de la sesión de Auth (panel administrativo): usa su propia
 * clave en $_SESSION y nunca se cruza con usuario_id/usuario_rol.
 *
 * Un PIN de 4 dígitos tiene muy poca entropía (10 000 combinaciones),
 * así que aplica exactamente el mismo endurecimiento que Auth::
 * intentar() — bloqueo por cuenta, límite por IP, comparación de
 * tiempo constante — porque aquí el riesgo de fuerza bruta es mayor,
 * no menor.
 */
class ClienteAuth
{
    private const MAX_INTENTOS_CUENTA = 5;
    private const BLOQUEO_MINUTOS = 15;

    private const MAX_INTENTOS_IP = 20;
    private const VENTANA_IP_MINUTOS = 15;

    private const HASH_FICTICIO = '$2y$10$Ldk/pjo2TTJuHMgYMjS/feP6CrMWdTyM8VMXO9mIj6wag3v.7iOMW';

    /** @return array{ok:bool,error?:string} */
    public static function intentar(string $telefono, string $pin): array
    {
        $ip = self::ipCliente();
        $intentos = new IntentoLogin();

        if ($intentos->contarFallidosRecientesPorIp($ip, self::VENTANA_IP_MINUTOS) >= self::MAX_INTENTOS_IP) {
            Logger::error("Login de cliente bloqueado por exceso de intentos desde la IP {$ip}");
            return ['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'];
        }

        $modelo = new Cliente();
        $registro = $modelo->buscarPorTelefono($telefono);

        $puedeIntentar = $registro !== null
            && !empty($registro['pin_hash'])
            && !self::estaBloqueado($registro);

        $hashContraComparar = $puedeIntentar ? $registro['pin_hash'] : self::HASH_FICTICIO;
        $valido = password_verify($pin, $hashContraComparar) && $puedeIntentar;

        $intentos->registrar($telefono, $valido, $ip, self::agenteUsuario());

        if (!$valido) {
            if ($registro !== null && !empty($registro['pin_hash'])) {
                $modelo->registrarIntentoPinFallido((int) $registro['id'], self::MAX_INTENTOS_CUENTA, self::BLOQUEO_MINUTOS);
            }
            return ['ok' => false, 'error' => 'Teléfono o PIN incorrectos.'];
        }

        Session::regenerar();
        // Token CSRF nuevo para la sesión ya autenticada: el del formulario de
        // login (anónimo) no debe seguir valiendo dentro de la cuenta.
        unset($_SESSION['csrf_token']);
        $_SESSION['cliente_id']     = (int) $registro['id'];
        $_SESSION['cliente_nombre'] = $registro['nombre'];

        $modelo->registrarAccesoClienteExitoso((int) $registro['id']);

        return ['ok' => true];
    }

    public static function verificado(): bool
    {
        return !empty($_SESSION['cliente_id']);
    }

    /** La sesión apunta a un cliente que todavía existe (puede haber sido eliminado desde el panel). */
    public static function clienteExiste(): bool
    {
        return self::verificado() && (new Cliente())->buscarPorId((int) $_SESSION['cliente_id']) !== null;
    }

    /**
     * Cierra la sesión de un cliente que ya no existe y lo manda al login con un aviso claro.
     * (El aviso se pone DESPUÉS de destruir la sesión vieja, en una sesión nueva.)
     */
    public static function expulsarPorCuentaInexistente(): never
    {
        self::cerrarSesion();
        Session::iniciar();
        Flash::error('Tu cuenta ya no está disponible. Si crees que es un error, pregunta en la cafetería.');
        header('Location: /cuenta/login');
        exit;
    }

    /** @return array{id:int,nombre:string}|null */
    public static function cliente(): ?array
    {
        if (!self::verificado()) {
            return null;
        }

        return [
            'id'     => (int) $_SESSION['cliente_id'],
            'nombre' => (string) $_SESSION['cliente_nombre'],
        ];
    }

    public static function cerrarSesion(): void
    {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre']);
        Session::destruir();
    }

    private static function estaBloqueado(array $registro): bool
    {
        return !empty($registro['pin_bloqueado_hasta']) && strtotime($registro['pin_bloqueado_hasta']) > time();
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
