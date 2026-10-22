<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Configuracion;

/**
 * Qué productos permiten elegir leche en el carrito. Se guarda como una lista de id en
 * configuracion_sistema.leche_productos (igual que la temperatura, TemperaturaProductos): no hace falta
 * modificar la tabla `productos`. Se edita en Productos (casilla «Permite elegir leche») y en Leches.
 * Compatible con PHP 7.4.
 */
class LechesProductos
{
    public const CLAVE = 'leche_productos';
    private const DESCRIPCION = 'Productos que permiten elegir leche (lista de id en JSON)';

    /**
     * @param array<string,string> $config resultado de Configuracion::obtenerTodas()
     * @return array<int,int> id de producto => id (para consultar con isset)
     */
    public static function mapa(array $config): array
    {
        $json = json_decode((string) (isset($config[self::CLAVE]) ? $config[self::CLAVE] : ''), true);
        if (!is_array($json)) {
            return [];
        }

        $mapa = [];
        foreach ($json as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $mapa[(int) $id] = (int) $id;
            }
        }

        return $mapa;
    }

    public static function permite(array $config, int $productoId): bool
    {
        return isset(self::mapa($config)[$productoId]);
    }

    /** Pone o quita la leche de un producto. */
    public static function marcar(int $productoId, bool $permite): void
    {
        $mapa = self::mapa((new Configuracion())->obtenerTodas());
        if ($permite) {
            $mapa[$productoId] = $productoId;
        } else {
            unset($mapa[$productoId]);
        }
        self::guardarLista(array_values($mapa));
    }

    /**
     * Reemplaza la lista completa (pantalla Leches). Descarta ids que no existan como producto.
     *
     * @param array<int,int|string> $ids
     */
    public static function reemplazar(array $ids): void
    {
        $limpios = [];
        foreach ($ids as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $limpios[(int) $id] = (int) $id;
            }
        }
        if ($limpios !== []) {
            $marcas = implode(',', array_fill(0, count($limpios), '?'));
            $stmt = Database::conexion()->prepare("SELECT id FROM productos WHERE id IN ({$marcas})");
            $stmt->execute(array_values($limpios));
            $existentes = [];
            foreach ($stmt->fetchAll() as $f) {
                $existentes[(int) $f['id']] = (int) $f['id'];
            }
            $limpios = array_intersect_key($limpios, $existentes);
        }

        self::guardarLista(array_values($limpios));
    }

    /** @param array<int,int> $ids */
    private static function guardarLista(array $ids): void
    {
        sort($ids);
        Database::conexion()->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES (:clave, :valor, :descripcion)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute(['clave' => self::CLAVE, 'valor' => (string) json_encode($ids), 'descripcion' => self::DESCRIPCION]);
    }
}
