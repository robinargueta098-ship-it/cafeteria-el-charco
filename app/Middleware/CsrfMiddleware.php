<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;

/**
 * Valida el token CSRF en cada POST. Se llama al inicio de toda acción
 * de controlador que procese un formulario.
 */
class CsrfMiddleware
{
    public static function manejar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $token = $_POST['csrf_token'] ?? null;

        if (!Csrf::validar($token)) {
            http_response_code(403);
            require APP_PATH . '/Views/errores/403.php';
            exit;
        }
    }
}
