<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Arranque de sesión con cookies seguras (docs/arquitectura.md §7):
 * HttpOnly, SameSite=Strict y Secure en producción.
 */
class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

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
