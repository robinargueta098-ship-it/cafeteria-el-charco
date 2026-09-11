<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF para todo formulario POST del panel (docs/arquitectura.md §7).
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /** Devuelve el <input type="hidden"> listo para imprimir en una vista. */
    public static function campo(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    public static function validar(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
