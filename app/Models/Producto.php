<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Producto
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             ORDER BY c.orden ASC, p.orden ASC, p.nombre ASC'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Solo productos activos, para selects (ej. canje de lealtad). */
    public function listarActivos(): array
    {
        return Database::conexion()->query(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1
             ORDER BY c.orden ASC, p.orden ASC, p.nombre ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM productos WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO productos
                (categoria_id, nombre, descripcion, precio, precio_alt, etiqueta_precio_alt,
                 imagen, es_destacado, es_mas_vendido, activo, orden)
             VALUES
                (:categoria_id, :nombre, :descripcion, :precio, :precio_alt, :etiqueta_precio_alt,
                 :imagen, :es_destacado, :es_mas_vendido, :activo, :orden)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE productos SET
                categoria_id = :categoria_id, nombre = :nombre, descripcion = :descripcion,
                precio = :precio, precio_alt = :precio_alt, etiqueta_precio_alt = :etiqueta_precio_alt,
                imagen = :imagen, es_destacado = :es_destacado, es_mas_vendido = :es_mas_vendido,
                activo = :activo, orden = :orden
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM productos WHERE id = :id')->execute(['id' => $id]);
    }

    /** Referencias que impiden borrado físico (canjes de lealtad ya hechos con este producto). */
    public function tieneReferenciasDeLealtad(int $id): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM lealtad_movimientos WHERE producto_canjeado_id = :id'
        );
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function contarActivos(): int
    {
        return (int) Database::conexion()->query('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn();
    }

    public function categoriaExiste(int $categoriaId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM categorias WHERE id = :id');
        $stmt->execute(['id' => $categoriaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // --- Temporadas asociadas (producto_temporadas, N:M) ---

    /** @return array<int,int> IDs de temporada asociados a este producto. */
    public function obtenerTemporadaIds(int $productoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT temporada_id FROM producto_temporadas WHERE producto_id = :id'
        );
        $stmt->execute(['id' => $productoId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @param array<int,int> $temporadaIds */
    public function sincronizarTemporadas(int $productoId, array $temporadaIds): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM producto_temporadas WHERE producto_id = :id')->execute(['id' => $productoId]);

        if ($temporadaIds === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO producto_temporadas (producto_id, temporada_id) VALUES (:producto_id, :temporada_id)'
        );

        foreach (array_unique($temporadaIds) as $temporadaId) {
            $stmt->execute(['producto_id' => $productoId, 'temporada_id' => $temporadaId]);
        }
    }
}
