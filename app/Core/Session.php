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
            session_destroy();
        }
    }
}
