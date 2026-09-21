<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Arranque de sesión con cookies seguras (docs/arquitectura.md §7):
 * HttpOnly, SameSite=Strict y Secure en producción.
 */
class Session
{
    /** Inactividad tras la cual la sesión del panel vence (8 horas: una jornada). */
    private const VIDA_SEGUNDOS = 28800;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Cookie y carpeta propias: con el nombre por defecto (PHPSESSID) y la
        // carpeta compartida de XAMPP (C:\xampp\tmp), cualquier otra aplicación
        // PHP del mismo servidor podía pisar la cookie o borrar nuestros
        // archivos de sesión a los 24 min (session.gc_maxlifetime por defecto).
        session_name('ELCHARCO_SESION');

        $carpeta = BASE_PATH . '/storage/sessions';
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0700, true);
        }
        if (is_dir($carpeta) && is_writable($carpeta)) {
            session_save_path($carpeta);
        }

        ini_set('session.gc_maxlifetime', (string) self::VIDA_SEGUNDOS);
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => APP_ENV === 'production',
        ]);

        session_start();
    }

    public static function regenerar(): void
    {
        session_regenerate_id(true);
    }

    public static function destruir(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            // Además de borrar los datos en el servidor, se expira la
            // cookie en el navegador para no dejarla "viva" sin motivo.
            if (ini_get('session.use_cookies')) {
                $parametros = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $parametros['path'],
                    $parametros['domain'],
                    $parametros['secure'],
                    $parametros['httponly']
                );
            }

            session_destroy();
        }
    }
}
