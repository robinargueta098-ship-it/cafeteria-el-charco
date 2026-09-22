<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Temporada
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()
            ->query('SELECT * FROM temporadas ORDER BY fecha_inicio DESC')
            ->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Vigentes hoy y activas (para el sitio público, Fase 7). */
    public function listarVigentes(): array
    {
        return Database::conexion()->query(
            'SELECT * FROM temporadas
             WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin
             ORDER BY fecha_inicio ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM temporadas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO temporadas (nombre, descripcion, imagen, fecha_inicio, fecha_fin, activo)
             VALUES (:nombre, :descripcion, :imagen, :fecha_inicio, :fecha_fin, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE temporadas
             SET nombre = :nombre, descripcion = :descripcion, imagen = :imagen,
                 fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin, activo = :activo
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM temporadas WHERE id = :id')->execute(['id' => $id]);
    }

    public function contarProductosAsociados(int $id): int
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM producto_temporadas WHERE temporada_id = :id'
        );
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }
}
