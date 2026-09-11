<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/**
 * Control de acceso por rol (docs/arquitectura.md §3.2 — Administrador
 * vs Cajero). Los IDs de rol se definen al sembrar la tabla `roles`
 * (1 = Administrador, 2 = Cajero, ver database/schema.sql).
 */
class RoleMiddleware
{
    public static function requerir(int ...$rolesPermitidos): void
    {
        $usuario = Auth::usuario();

        if (!$usuario || !in_array($usuario['rol_id'], $rolesPermitidos, true)) {
            http_response_code(403);
            require APP_PATH . '/Views/errores/403.php';
            exit;
        }
    }
}
