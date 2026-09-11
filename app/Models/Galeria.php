<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Galeria
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()->query('SELECT * FROM galeria ORDER BY orden ASC, id DESC')->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM galeria WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO galeria (imagen, titulo, descripcion, orden, activo)
             VALUES (:imagen, :titulo, :descripcion, :orden, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE galeria SET imagen = :imagen, titulo = :titulo, descripcion = :descripcion, orden = :orden, activo = :activo
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM galeria WHERE id = :id')->execute(['id' => $id]);
    }
}
