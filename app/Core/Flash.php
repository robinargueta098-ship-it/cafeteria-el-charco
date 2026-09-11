<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Mensajes de una sola lectura tras un redirect (patrón POST/Redirect/GET):
 * se guardan en sesión antes de redirigir y se leen (y se borran) en la
 * siguiente petición.
 */
class Flash
{
    public static function exito(string $mensaje): void
    {
        self::guardar('exito', $mensaje);
    }

    public static function error(string $mensaje): void
    {
        self::guardar('error', $mensaje);
    }

    private static function guardar(string $tipo, string $mensaje): void
    {
        $_SESSION['_flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    /** @return array{tipo:string,mensaje:string}|null */
    public static function tomar(): ?array
    {
        $mensaje = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return $mensaje;
    }
}
