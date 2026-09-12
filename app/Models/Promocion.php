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

    /**
     * @return array<int,array<string,mixed>> Promociones activas, vigentes hoy, que aplican
     * al día de la semana y hora actuales (docs/arquitectura.md §4.3 — se calcula siempre en
     * el servidor, nunca se confía en el reloj del navegador). Para mostrar al público, no
     * para resolver conflictos de precio (este sitio no tiene checkout).
     */
    public function listarVigentesAhora(): array
    {
        $diaSemana = (int) date('w');
        $horaActual = date('H:i:s');

        $filas = Database::conexion()->query(
            "SELECT pr.*, c.nombre AS categoria_nombre, p.nombre AS producto_nombre
             FROM promociones pr
             LEFT JOIN categorias c ON c.id = pr.categoria_id
             LEFT JOIN productos p ON p.id = pr.producto_id
             WHERE pr.activo = 1 AND CURDATE() BETWEEN pr.fecha_inicio AND pr.fecha_fin
             ORDER BY pr.prioridad DESC, pr.fecha_inicio ASC"
        )->fetchAll();

        return array_values(array_filter($filas, function (array $promo) use ($diaSemana, $horaActual): bool {
            if ($promo['dias_semana'] !== null) {
                $dias = array_map('intval', explode(',', (string) $promo['dias_semana']));
                if (!in_array($diaSemana, $dias, true)) {
                    return false;
                }
            }

            if ($promo['hora_inicio'] !== null && $horaActual < $promo['hora_inicio']) {
                return false;
            }
            if ($promo['hora_fin'] !== null && $horaActual > $promo['hora_fin']) {
                return false;
            }

            return true;
        }));
    }

    public function contarVigentesHoy(): int
    {
        return (int) Database::conexion()->query(
            'SELECT COUNT(*) FROM promociones WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin'
        )->fetchColumn();
    }
}
