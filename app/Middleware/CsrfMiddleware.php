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

        // Si el cuerpo de la petición superó post_max_size, PHP descarta
        // $_POST y $_FILES por completo: sin este chequeo, el token CSRF
        // parecería "faltante" y el usuario vería un 403 engañoso en vez
        // de saber que sus archivos fueron demasiado pesados.
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
            http_response_code(413);
            require APP_PATH . '/Views/errores/413.php';
            exit;
        }

        $token = $_POST['csrf_token'] ?? null;

        if (!Csrf::validar($token)) {
            http_response_code(403);
            require APP_PATH . '/Views/errores/403.php';
            exit;
        }
    }
}
