<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Configuracion;

/**
 * Temperatura en que se puede pedir cada producto: 'ambas' (caliente o frío, el cliente elige al
 * agregarlo al carrito), 'caliente', 'frio', o ninguna (productos donde no aplica, como un sándwich).
 *
 * Se guarda como un mapa {id_producto: temperatura} en configuracion_sistema.temperaturas_productos
 * y se edita desde Productos → "Temperatura". Así no hace falta modificar la tabla `productos`
 * (la cuenta de la aplicación no tiene permiso de ALTER TABLE).
 */
class TemperaturaProductos
{
    public const CLAVE = 'temperaturas_productos';
    public const VALIDAS = ['ambas', 'caliente', 'frio'];

    /**
     * @param array<string,string> $config resultado de Configuracion::obtenerTodas()
     * @return array<int,string> id de producto => temperatura
     */
    public static function mapa(array $config): array
    {
        $json = json_decode((string) ($config[self::CLAVE] ?? ''), true);
        if (!is_array($json)) {
            return [];
        }

        $mapa = [];
        foreach ($json as $id => $valor) {
            if (is_string($valor) && in_array($valor, self::VALIDAS, true)) {
                $mapa[(int) $id] = $valor;
            }
        }

        return $mapa;
    }

    /** Guarda la temperatura de un producto; '' la quita (el producto deja de tener la opción). */
    public static function guardar(int $productoId, string $temperatura): void
    {
        $configuracion = new Configuracion();
        $mapa = self::mapa($configuracion->obtenerTodas());

        if (in_array($temperatura, self::VALIDAS, true)) {
            $mapa[$productoId] = $temperatura;
        } else {
            unset($mapa[$productoId]);
        }

        ksort($mapa);
        // Se hace upsert porque Configuracion::actualizar() solo toca claves que ya existen.
        Database::conexion()->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, descripcion)
             VALUES (:clave, :valor, :descripcion)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute([
            'clave'       => self::CLAVE,
            'valor'       => (string) json_encode($mapa, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT),
            'descripcion' => 'Temperatura en que se puede pedir cada producto (id => ambas|caliente|frio)',
        ]);
    }
}
