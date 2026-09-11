<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/**
 * Exige que haya una sesión de panel activa; si no, redirige al login.
 * Se llama al inicio de cada acción de controlador que requiera sesión.
 */
class AuthMiddleware
{
    public static function manejar(): void
    {
        if (!Auth::verificado()) {
            header('Location: /admin/login');
            exit;
        }
    }
}
