<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Captura errores y excepciones no manejadas: se registran en el log
 * y al usuario solo se le muestra un mensaje genérico (regla de
 * seguridad de docs/arquitectura.md §7 — nunca mostrar detalles
 * internos). Incluye un manejador de "shutdown" porque algunos errores
 * fatales del motor de PHP no pasan ni por set_exception_handler ni
 * por set_error_handler.
 */
class ErrorHandler
{
    /** Niveles que PHP considera fatales y que solo se ven vía register_shutdown_function. */
    private const NIVELES_FATALES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public static function registrar(): void
    {
        // display_errors se decide aquí y no se confía en el php.ini del
        // hosting: en producción nunca debe imprimir nada al navegador.
        ini_set('display_errors', APP_DEBUG ? '1' : '0');
        ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
        error_reporting(E_ALL);

        set_exception_handler([self::class, 'manejarExcepcion']);
        set_error_handler([self::class, 'manejarError']);
        register_shutdown_function([self::class, 'manejarApagado']);
    }

    public static function manejarExcepcion(Throwable $e): void
    {
        Logger::error($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        self::responder500((string) $e);
    }

    public static function manejarError(int $nivel, string $mensaje, string $archivo, int $linea): bool
    {
        // Respeta el operador "@" y el error_reporting configurado: si el
        // nivel no está incluido, no se registra ni se actúa.
        if (!(error_reporting() & $nivel)) {
            return true;
        }

        Logger::error("$mensaje en $archivo:$linea");

        return true; // evita que PHP también ejecute su manejador nativo
    }

    /**
     * Última red de seguridad: errores fatales del motor (memoria
     * agotada, error interno, etc.) que no llegan a set_error_handler
     * ni a set_exception_handler.
     */
    public static function manejarApagado(): void
    {
        $error = error_get_last();

        if ($error === null || !(self::NIVELES_FATALES & $error['type'])) {
            return;
        }

        Logger::error("{$error['message']} en {$error['file']}:{$error['line']}");
        self::responder500($error['message']);
    }

    private static function responder500(string $detalle): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (APP_DEBUG) {
            echo '<pre style="padding:16px;white-space:pre-wrap;">'
                . htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8')
                . '</pre>';
            return;
        }

        require APP_PATH . '/Views/errores/500.php';
    }
}
