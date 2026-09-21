<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Orden manual (flechas ▲▼) de las listas de imágenes que se administran
 * desde el panel: galería y portada. Ambas usan la misma regla, así que
 * vive en un solo lugar.
 *
 * El nombre de tabla NO se puede pasar como parámetro de una sentencia
 * preparada, así que se valida contra una lista blanca fija: nunca se
 * concatena nada que venga de fuera.
 */
class OrdenLista
{
    private const TABLAS_PERMITIDAS = ['galeria', 'portada_imagenes'];

    /** Posición para un elemento nuevo: al final de la lista. */
    public static function siguiente(string $tabla): int
    {
        self::validarTabla($tabla);

        return (int) Database::conexion()->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM {$tabla}")->fetchColumn();
    }

    /**
     * Intercambia un elemento con su vecino ('subir' o 'bajar').
     *
     * Antes de intercambiar renumera TODOS como 1..n según su orden
     * actual: así funciona aunque haya empates o huecos en `orden`
     * (filas antiguas que quedaron todas en 0), en vez de "intercambiar"
     * dos números iguales sin lograr nada. Todo dentro de una
     * transacción con las filas bloqueadas, para que dos clics
     * simultáneos no se pisen.
     *
     * @return array{resultado:string,desde?:int,hasta?:int} resultado: 'movida' | 'extremo' | 'no_existe'
     */
    public static function mover(string $tabla, int $id, string $direccion): array
    {
        self::validarTabla($tabla);

        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $ids = array_map(
                'intval',
                $pdo->query("SELECT id FROM {$tabla} ORDER BY orden ASC, id ASC FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN)
            );

            $posicion = array_search($id, $ids, true);
            if ($posicion === false) {
                $pdo->rollBack();
                return ['resultado' => 'no_existe'];
            }

            $destino = $direccion === 'subir' ? $posicion - 1 : $posicion + 1;
            if ($destino < 0 || $destino >= count($ids)) {
                $pdo->rollBack();
                return ['resultado' => 'extremo'];
            }

            [$ids[$posicion], $ids[$destino]] = [$ids[$destino], $ids[$posicion]];

            $stmt = $pdo->prepare("UPDATE {$tabla} SET orden = :orden WHERE id = :id");
            foreach ($ids as $indice => $idElemento) {
                $stmt->execute(['orden' => $indice + 1, 'id' => $idElemento]);
            }

            $pdo->commit();

            return ['resultado' => 'movida', 'desde' => $posicion + 1, 'hasta' => $destino + 1];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function validarTabla(string $tabla): void
    {
        if (!in_array($tabla, self::TABLAS_PERMITIDAS, true)) {
            throw new InvalidArgumentException("Tabla no permitida para ordenar: {$tabla}");
        }
    }
}
