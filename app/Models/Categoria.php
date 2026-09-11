<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Categoria
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()
            ->query('SELECT * FROM categorias ORDER BY orden ASC, nombre ASC')
            ->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listarActivas(): array
    {
        return Database::conexion()
            ->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY orden ASC, nombre ASC')
            ->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM categorias WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function buscarPorSlug(string $slug, ?int $excluirId = null): ?array
    {
        $sql = 'SELECT * FROM categorias WHERE slug = :slug';
        $parametros = ['slug' => $slug];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros['excluir_id'] = $excluirId;
        }

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO categorias (nombre, slug, descripcion, notas, orden, activo)
             VALUES (:nombre, :slug, :descripcion, :notas, :orden, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE categorias
             SET nombre = :nombre, slug = :slug, descripcion = :descripcion,
                 notas = :notas, orden = :orden, activo = :activo
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM categorias WHERE id = :id')->execute(['id' => $id]);
    }

    public function contarProductos(int $id): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM productos WHERE categoria_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    /** Genera un slug único a partir del nombre, agregando -2, -3... si ya existe. */
    public function generarSlugUnico(string $nombre, ?int $excluirId = null): string
    {
        $base = self::slugificar($nombre);
        $slug = $base;
        $intento = 2;

        while ($this->buscarPorSlug($slug, $excluirId) !== null) {
            $slug = $base . '-' . $intento;
            $intento++;
        }

        return $slug;
    }

    private static function slugificar(string $texto): string
    {
        $texto = strtolower(trim($texto));
        $texto = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        return $texto !== '' ? $texto : 'categoria';
    }
}
