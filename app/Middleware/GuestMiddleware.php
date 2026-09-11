<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/**
 * Lo contrario de AuthMiddleware: si ya hay sesión, no tiene sentido
 * mostrar el formulario de login de nuevo.
 */
class GuestMiddleware
{
    public static function manejar(): void
    {
        if (Auth::verificado()) {
            header('Location: /admin');
            exit;
        }
    }
}
