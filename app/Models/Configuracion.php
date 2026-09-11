<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Acceso a la tabla clave/valor `configuracion_sistema`: nombre del
 * sitio, logo, WhatsApp, redes, mapa, textos, etc. (ver
 * docs/modelo-datos.md). La edición desde el panel se construye en
 * una fase posterior; por ahora solo lectura y actualización simple.
 */
class Configuracion
{
    /** @return array<string,string> clave => valor */
    public function obtenerTodas(): array
    {
        $stmt = Database::conexion()->query('SELECT clave, valor FROM configuracion_sistema');

        $config = [];
        foreach ($stmt->fetchAll() as $fila) {
            $config[$fila['clave']] = (string) $fila['valor'];
        }

        return $config;
    }

    public function actualizar(string $clave, string $valor): void
    {
        Database::conexion()
            ->prepare('UPDATE configuracion_sistema SET valor = :valor WHERE clave = :clave')
            ->execute(['valor' => $valor, 'clave' => $clave]);
    }

    /** @param array<string,string> $pares clave => valor. Solo actualiza claves que ya existen (no crea nuevas). */
    public function actualizarMultiples(array $pares): void
    {
        $stmt = Database::conexion()->prepare(
            'UPDATE configuracion_sistema SET valor = :valor WHERE clave = :clave'
        );

        foreach ($pares as $clave => $valor) {
            $stmt->execute(['valor' => $valor, 'clave' => $clave]);
        }
    }
}
