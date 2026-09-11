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
        $directorioVistas = realpath(APP_PATH . '/Views');
        $rutaArchivo = realpath(APP_PATH . '/Views/' . $vista . '.php');

        // Defensa en profundidad: aunque hoy $vista siempre es un valor
        // fijo en el código (nunca viene de la URL o de un formulario),
        // esto evita que un futuro descuido abra inclusión de archivos
        // arbitrarios (ej. $vista = '../../../../config/database').
        if (
            $directorioVistas === false
            || $rutaArchivo === false
            || !str_starts_with($rutaArchivo, $directorioVistas . DIRECTORY_SEPARATOR)
        ) {
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
