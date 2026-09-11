<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Promocion
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()->query(
            "SELECT pr.*, c.nombre AS categoria_nombre, p.nombre AS producto_nombre
             FROM promociones pr
             LEFT JOIN categorias c ON c.id = pr.categoria_id
             LEFT JOIN productos p ON p.id = pr.producto_id
             ORDER BY pr.fecha_inicio DESC"
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM promociones WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO promociones
                (nombre, descripcion, tipo, valor, categoria_id, producto_id,
                 fecha_inicio, fecha_fin, dias_semana, hora_inicio, hora_fin,
                 combinable_con_lealtad, prioridad, activo)
             VALUES
                (:nombre, :descripcion, :tipo, :valor, :categoria_id, :producto_id,
                 :fecha_inicio, :fecha_fin, :dias_semana, :hora_inicio, :hora_fin,
                 :combinable_con_lealtad, :prioridad, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE promociones SET
                nombre = :nombre, descripcion = :descripcion, tipo = :tipo, valor = :valor,
                categoria_id = :categoria_id, producto_id = :producto_id,
                fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin,
                dias_semana = :dias_semana, hora_inicio = :hora_inicio, hora_fin = :hora_fin,
                combinable_con_lealtad = :combinable_con_lealtad, prioridad = :prioridad, activo = :activo
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM promociones WHERE id = :id')->execute(['id' => $id]);
    }

    public function contarVigentesHoy(): int
    {
        return (int) Database::conexion()->query(
            'SELECT COUNT(*) FROM promociones WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin'
        )->fetchColumn();
    }
}
