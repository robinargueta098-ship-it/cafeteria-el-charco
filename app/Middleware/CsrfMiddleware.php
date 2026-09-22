<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\ClienteAuth;
use App\Core\Csrf;
use App\Core\Flash;

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
            self::tokenInvalido();
        }
    }

    /**
     * Un token inválido casi nunca es un ataque: lo normal es una sesión
     * vencida por inactividad, o una pantalla vieja que quedó abierta en
     * otra pestaña después de cerrar sesión. Mostrar un "403" sin salida
     * dejaba al usuario atrapado (F5 reenvía el mismo POST y vuelve a
     * fallar). En el panel y en la cuenta del cliente se redirige con un
     * aviso a una pantalla GET, que sí carga con un token nuevo; el POST
     * rechazado no se ejecuta.
     */
    private static function tokenInvalido(): void
    {
        $ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if ($ruta === '/admin' || str_starts_with($ruta, '/admin/')) {
            if (Auth::verificado()) {
                Flash::error('La página estaba desactualizada y no se guardó nada. Vuelve a intentarlo.');
                header('Location: /admin');
            } else {
                Flash::error('Tu sesión expiró. Inicia sesión de nuevo.');
                header('Location: /admin/login');
            }
            exit;
        }

        if ($ruta === '/cuenta' || str_starts_with($ruta, '/cuenta/')) {
            if (ClienteAuth::verificado()) {
                Flash::error('La página estaba desactualizada y no se guardó nada. Vuelve a intentarlo.');
                header('Location: /cuenta');
            } else {
                Flash::error('Tu sesión expiró. Inicia sesión de nuevo.');
                header('Location: /cuenta/login');
            }
            exit;
        }

        http_response_code(403);
        require APP_PATH . '/Views/errores/403.php';
        exit;
    }
}
