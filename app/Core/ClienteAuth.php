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
 * así que aquí el riesgo de fuerza bruta es mayor que en el panel:
 *  - bloqueo escalonado por teléfono + IP (ver segundosDeBloqueo()),
 *  - límite general de fallidos por IP,
 *  - comparación de tiempo constante (se verifica un hash aunque el teléfono no exista).
 *
 * PIN temporal: lo genera el personal en persona (LealtadController::restablecerPin()). Quien
 * entra con él no llega a su cuenta: queda en un estado "pendiente" y debe crear un PIN nuevo
 * (CuentaController::cambiarPin). Las sesiones abiertas guardan una huella del PIN vigente; al
 * cambiar el PIN, todas las anteriores dejan de valer.
 */
class ClienteAuth
{
    /** Cada 5 fallidos seguidos hay un bloqueo: 20 s el primero, 60 s (el máximo) los siguientes. */
    private const FALLOS_POR_BLOQUEO = 5;
    private const BLOQUEO_PRIMERO_SEGUNDOS = 20;
    private const BLOQUEO_MAXIMO_SEGUNDOS = 60;
    /** Sin fallidos durante este tiempo, la racha y el nivel de bloqueo vuelven a cero. */
    private const REINICIO_SEGUNDOS = 30 * 60;

    private const MAX_INTENTOS_IP = 20;
    private const VENTANA_IP_MINUTOS = 15;

    private const HASH_FICTICIO = '$2y$10$Ldk/pjo2TTJuHMgYMjS/feP6CrMWdTyM8VMXO9mIj6wag3v.7iOMW';

    /**
     * Segundos que faltan para poder volver a intentar con ese teléfono desde esa IP (0 = libre).
     * El bloqueo se deduce del historial de `intentos_login`: tras el fallido 5 → 20 s; tras el 10,
     * 15, 20… → 60 s. Mientras dura el bloqueo los intentos se rechazan sin registrarse ni
     * verificarse (ni con el PIN correcto), así que el conteo no se desplaza.
     */
    public static function segundosDeBloqueo(string $telefono, string $ip): int
    {
        return self::estadoDeBloqueo($telefono, $ip)['segundos'];
    }

    /** @return array{fallos:int,segundos:int} fallidos seguidos de la racha actual y segundos de bloqueo que faltan */
    private static function estadoDeBloqueo(string $telefono, string $ip): array
    {
        $racha = (new IntentoLogin())->rachaDeFallos($telefono, $ip, self::REINICIO_SEGUNDOS);
        $fallos = $racha['fallos'];

        if ($fallos === 0 || $fallos % self::FALLOS_POR_BLOQUEO !== 0) {
            return ['fallos' => $fallos, 'segundos' => 0];
        }

        $duracion = $fallos === self::FALLOS_POR_BLOQUEO ? self::BLOQUEO_PRIMERO_SEGUNDOS : self::BLOQUEO_MAXIMO_SEGUNDOS;

        return ['fallos' => $fallos, 'segundos' => max(0, $duracion - $racha['hace_segundos'])];
    }

    public static function mensajeBloqueo(int $segundos): string
    {
        return "Demasiados intentos fallidos. Intenta de nuevo en {$segundos} segundos o acércate a la cafetería para que te ayudemos.";
    }

    /** @return array{ok:bool,error?:string,cambiar_pin?:bool,segundos?:int} segundos = bloqueo vigente (la pantalla lo va descontando) */
    public static function intentar(string $telefono, string $pin): array
    {
        $ip = self::ipCliente();
        $intentos = new IntentoLogin();

        if ($intentos->contarFallidosRecientesPorIp($ip, self::VENTANA_IP_MINUTOS) >= self::MAX_INTENTOS_IP) {
            Logger::error("Login de cliente bloqueado por exceso de intentos desde la IP {$ip}");
            return ['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.'];
        }

        $estado = self::estadoDeBloqueo($telefono, $ip);
        if ($estado['segundos'] > 0) {
            return ['ok' => false, 'error' => self::mensajeBloqueo($estado['segundos']), 'segundos' => $estado['segundos']];
        }

        $modelo = new Cliente();
        $registro = $modelo->buscarPorTelefono($telefono);

        $puedeIntentar = $registro !== null && !empty($registro['pin_hash']);

        $hashContraComparar = $puedeIntentar ? Cliente::hashDelPin($registro) : self::HASH_FICTICIO;
        $valido = password_verify($pin, $hashContraComparar) && $puedeIntentar;

        $intentos->registrar($telefono, $valido, $ip, self::agenteUsuario());

        if (!$valido) {
            // Si este fallido completa un grupo de 5, se avisa ya del bloqueo (con los segundos reales).
            $estado = self::estadoDeBloqueo($telefono, $ip);
            // Pasados los 5 primeros fallidos se muestra el aviso "acércate a la cafetería" (se recuerda en la sesión).
            if ($estado['fallos'] > self::FALLOS_POR_BLOQUEO) {
                $_SESSION['cliente_login_ayuda'] = true;
            }
            return $estado['segundos'] > 0
                ? ['ok' => false, 'error' => self::mensajeBloqueo($estado['segundos']), 'segundos' => $estado['segundos']]
                : ['ok' => false, 'error' => 'Teléfono o PIN incorrectos.'];
        }

        if (Cliente::pinEsTemporal($registro)) {
            // PIN temporal: todavía no entra a su cuenta; primero debe crear un PIN nuevo.
            Session::regenerar();
            unset($_SESSION['csrf_token'], $_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_huella']);
            $_SESSION['cliente_cambio_pin'] = ['id' => (int) $registro['id'], 'huella' => Cliente::huellaPin($registro)];

            return ['ok' => true, 'cambiar_pin' => true];
        }

        self::abrirSesion((int) $registro['id'], (string) $registro['nombre'], (string) $registro['pin_hash']);

        return ['ok' => true];
    }

    /** Abre la sesión del cliente ya autenticado (cuenta completa), atada al PIN vigente. */
    public static function abrirSesion(int $clienteId, string $nombre, string $pinHash): void
    {
        Session::regenerar();
        // Token CSRF nuevo para la sesión ya autenticada: el del formulario de
        // login (anónimo) no debe seguir valiendo dentro de la cuenta.
        unset($_SESSION['csrf_token'], $_SESSION['cliente_cambio_pin'], $_SESSION['cliente_login_ayuda']);
        $_SESSION['cliente_id']     = $clienteId;
        $_SESSION['cliente_nombre'] = $nombre;
        $_SESSION['cliente_huella'] = Cliente::huellaPin(['pin_hash' => $pinHash]);

        (new Cliente())->registrarAccesoClienteExitoso($clienteId);
    }

    /**
     * Cliente que entró con un PIN temporal y todavía debe crear el nuevo, o null si no hay un
     * cambio pendiente válido (sesión vencida, PIN restablecido otra vez, o ya lo cambió).
     *
     * @return array<string,mixed>|null
     */
    public static function pendienteDeCambiarPin(): ?array
    {
        $pendiente = $_SESSION['cliente_cambio_pin'] ?? null;
        if (!is_array($pendiente) || !isset($pendiente['id'], $pendiente['huella'])) {
            return null;
        }

        $registro = (new Cliente())->buscarPorId((int) $pendiente['id']);
        if ($registro === null || !Cliente::pinEsTemporal($registro)
            || !hash_equals(Cliente::huellaPin($registro), (string) $pendiente['huella'])) {
            unset($_SESSION['cliente_cambio_pin']);
            return null;
        }

        return $registro;
    }

    public static function verificado(): bool
    {
        return !empty($_SESSION['cliente_id']);
    }

    /**
     * Comprueba que la sesión abierta siga valiendo: el cliente existe (el Administrador pudo
     * eliminarlo) y su PIN no cambió desde que entró (un restablecimiento cierra todas sus sesiones).
     * Si no, la cierra y lo manda al login con un aviso claro.
     */
    public static function validarSesion(): void
    {
        $registro = (new Cliente())->buscarPorId((int) $_SESSION['cliente_id']);

        if ($registro === null) {
            self::expulsar('Tu cuenta ya no está disponible. Si crees que es un error, pregunta en la cafetería.');
        }

        if (Cliente::pinEsTemporal($registro)
            || !hash_equals(Cliente::huellaPin($registro), (string) ($_SESSION['cliente_huella'] ?? ''))) {
            self::expulsar('Por seguridad cerramos tu sesión porque tu PIN cambió. Inicia sesión de nuevo.');
        }
    }

    /** Cierra la sesión del cliente y lo manda al login con un aviso (puesto DESPUÉS de destruir la sesión vieja). */
    public static function expulsar(string $mensaje): void
    {
        self::cerrarSesion();
        Session::iniciar();
        Flash::error($mensaje);
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
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_huella'], $_SESSION['cliente_cambio_pin']);
        Session::destruir();
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
