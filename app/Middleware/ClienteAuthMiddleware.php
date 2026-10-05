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

        // Si el Administrador eliminó a este cliente, o su PIN se restableció/cambió mientras tenía la
        // sesión abierta, no se muestra la cuenta: se cierra la sesión y se le avisa.
        ClienteAuth::validarSesion();
    }
}
