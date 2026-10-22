<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Configuracion;

/**
 * Qué opciones de «Preparar con» (leches y agua) se ofrecen en cada producto.
 *
 *  - Una opción normal (`solo_productos = 0`, p. ej. las leches) se ofrece en todos los productos que permiten leche
 *    (LechesProductos, lista `leche_productos`).
 *  - Una opción restringida (`solo_productos = 1`, p. ej. Agua) se ofrece SOLO en los productos que se marcan para ella:
 *    lista por opción en `configuracion_sistema.leche_opciones_productos` = {"id de la opción": [id de productos]}.
 *
 * Un producto sin ninguna opción disponible no muestra el selector. Compatible con PHP 7.4.
 */
class OpcionesProducto
{
    public const CLAVE = 'leche_opciones_productos';
    private const DESCRIPCION = 'Productos en los que se ofrece cada opción de preparación restringida (id de la opción => lista de id de productos, en JSON)';

    /**
     * @param array<string,string> $config
     * @return array<int,array<int,int>> id de opción => (id de producto => id)
     */
    public static function listas(array $config): array
    {
        $json = json_decode((string) (isset($config[self::CLAVE]) ? $config[self::CLAVE] : ''), true);
        if (!is_array($json)) {
            return [];
        }

        $listas = [];
        foreach ($json as $opcionId => $ids) {
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                    $listas[(int) $opcionId][(int) $id] = (int) $id;
                }
            }
        }

        return $listas;
    }

    /**
     * Las opciones activas que se ofrecen en un producto, en el orden del panel.
     *
     * @param array<string,string> $config
     * @param array<int,array<string,mixed>> $opcionesActivas filas de `leches` activas (ya ordenadas)
     * @return array<int,array<string,mixed>>
     */
    public static function disponibles(array $config, array $opcionesActivas, int $productoId): array
    {
        $conLeche = LechesProductos::mapa($config);
        $listas = self::listas($config);

        $salida = [];
        foreach ($opcionesActivas as $opcion) {
            $id = (int) $opcion['id'];
            $restringida = (int) (isset($opcion['solo_productos']) ? $opcion['solo_productos'] : 0) === 1;
            $ofrecida = $restringida ? isset($listas[$id][$productoId]) : isset($conLeche[$productoId]);
            if ($ofrecida) {
                $salida[] = $opcion;
            }
        }

        return $salida;
    }

    /** Quita un producto de todas las listas (al eliminarlo). */
    public static function quitarProducto(int $productoId): void
    {
        $listas = self::listas((new Configuracion())->obtenerTodas());
        foreach ($listas as $opcionId => $lista) {
            if (isset($lista[$productoId])) {
                unset($lista[$productoId]);
                $guardar = array_values($lista);
                sort($guardar);
                $listas[$opcionId] = $lista;
            }
        }
        $salida = [];
        foreach ($listas as $id => $lista) {
            $valores = array_values($lista);
            sort($valores);
            $salida[(string) $id] = $valores;
        }
        Database::conexion()->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES (:clave, :valor, :descripcion)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute(['clave' => self::CLAVE, 'valor' => (string) json_encode((object) $salida), 'descripcion' => self::DESCRIPCION]);
    }

    /** Guarda los productos de una opción restringida. Descarta ids que no existan. */
    public static function guardarLista(int $opcionId, array $ids): void
    {
        $limpios = [];
        foreach ($ids as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $limpios[(int) $id] = (int) $id;
            }
        }
        if ($limpios !== []) {
            $stmt = Database::conexion()->prepare('SELECT id FROM productos WHERE id IN (' . implode(',', array_fill(0, count($limpios), '?')) . ')');
            $stmt->execute(array_values($limpios));
            $existentes = [];
            foreach ($stmt->fetchAll() as $f) {
                $existentes[(int) $f['id']] = (int) $f['id'];
            }
            $limpios = array_intersect_key($limpios, $existentes);
        }

        $listas = self::listas((new Configuracion())->obtenerTodas());
        $listas[$opcionId] = $limpios;
        $salida = [];
        foreach ($listas as $id => $lista) {
            $valores = array_values($lista);
            sort($valores);
            $salida[(string) $id] = $valores;
        }

        Database::conexion()->prepare(
            'INSERT INTO configuracion_sistema (clave, valor, descripcion) VALUES (:clave, :valor, :descripcion)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute(['clave' => self::CLAVE, 'valor' => (string) json_encode((object) $salida), 'descripcion' => self::DESCRIPCION]);
    }
}
