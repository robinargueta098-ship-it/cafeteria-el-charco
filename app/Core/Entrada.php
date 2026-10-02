<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lectura segura de parámetros de la petición. Una URL como ?q[]=a o un POST con nombre[]=x entrega
 * un ARREGLO donde el código espera texto, y convertirlo con (string) hace que PHP lance
 * "Array to string conversion". Aquí un valor que no es texto simple se trata como entrada inválida
 * (se devuelve el valor por defecto) y las validaciones de cada formulario lo rechazan con su mensaje.
 */
class Entrada
{
    /** @param array<string,mixed> $origen normalmente $_GET o $_POST */
    public static function texto(array $origen, string $clave, string $defecto = ''): string
    {
        $valor = $origen[$clave] ?? null;

        return is_string($valor) ? $valor : $defecto;
    }
}
