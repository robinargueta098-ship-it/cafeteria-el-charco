<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ClienteAuth;

/**
 * Exige una sesión de CLIENTE activa (no confundir con AuthMiddleware,
 * que es para el panel administrativo). Si no hay sesión, redirige al
 * login de la cuenta del cliente.
 */
class ClienteAuthMiddleware
{
    public static function manejar(): void
    {
        if (!ClienteAuth::verificado()) {
            header('Location: /cuenta/login');
            exit;
        }
    }
}
