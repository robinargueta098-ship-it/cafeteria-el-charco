<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Controlador base: solo ofrece atajos para renderizar vistas y
 * redirigir. La lógica de negocio real vive en app/Services, no aquí.
 */
abstract class Controller
{
    protected function vista(string $vista, array $datos = []): void
    {
        $rutaArchivo = APP_PATH . '/Views/' . $vista . '.php';

        if (!file_exists($rutaArchivo)) {
            throw new RuntimeException("Vista no encontrada: {$vista}");
        }

        extract($datos, EXTR_SKIP);
        require $rutaArchivo;
    }

    protected function redirigir(string $ruta): void
    {
        header('Location: ' . $ruta);
        exit;
    }
}
