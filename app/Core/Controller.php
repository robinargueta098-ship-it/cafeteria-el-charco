<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use RuntimeException;

/**
 * Controlador base: solo ofrece atajos para renderizar vistas y
 * redirigir. La lógica de negocio real vive en app/Services, no aquí.
 */
abstract class Controller
{
    /**
     * Exige sesión activa Y el rol indicado, en ese orden: sin esto,
     * un controlador podría llamar solo a RoleMiddleware y mostrar un
     * confuso "403" a alguien que ni siquiera había iniciado sesión
     * (debería ver el login). IDs de rol: 1 = Administrador, 2 = Cajero
     * (docs/arquitectura.md §3.2).
     */
    protected function requerirRol(int ...$rolesPermitidos): void
    {
        AuthMiddleware::manejar();
        RoleMiddleware::requerir(...$rolesPermitidos);
    }

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

    /** Para cuando un {id} de la URL no corresponde a ningún registro real. */
    protected function error404(): void
    {
        http_response_code(404);
        require APP_PATH . '/Views/errores/404.php';
        exit;
    }
}
