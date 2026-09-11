<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Captura errores y excepciones no manejadas: se registran en el log
 * y al usuario solo se le muestra un mensaje genérico (regla de
 * seguridad de docs/arquitectura.md §7 — nunca mostrar detalles
 * internos).
 */
class ErrorHandler
{
    public static function registrar(): void
    {
        set_exception_handler([self::class, 'manejarExcepcion']);
        set_error_handler([self::class, 'manejarError']);
    }

    public static function manejarExcepcion(Throwable $e): void
    {
        Logger::error($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

        http_response_code(500);

        if (APP_DEBUG) {
            echo '<pre style="padding:16px;white-space:pre-wrap;">'
                . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8')
                . '</pre>';
            return;
        }

        require APP_PATH . '/Views/errores/500.php';
    }

    public static function manejarError(int $nivel, string $mensaje, string $archivo, int $linea): bool
    {
        Logger::error("$mensaje en $archivo:$linea");
        // Devuelve true para que PHP no ejecute también su manejador nativo.
        return true;
    }
}
