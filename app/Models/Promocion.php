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
             ORDER BY pr.prioridad DESC, pr.fecha_inicio ASC, pr.id ASC"
        )->fetchAll();
    }

    /**
     * Sube o baja una promoción una posición en el orden en que se muestran en el sitio (el mismo del listado del
     * panel). El orden ES la prioridad: mayor prioridad = más arriba. Antes de intercambiar se renumera TODA la lista
     * (n, n-1, …, 1) según su orden actual, así funciona aunque haya empates; una promoción más arriba también gana
     * si dos aplican al mismo producto, que es justo lo que se espera de "la que más quiero mostrar".
     *
     * @return array{resultado:string,desde?:int,hasta?:int} 'movida' | 'extremo' | 'no_existe'
     */
    public function mover(int $id, string $direccion): array
    {
        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $ids = array_map('intval', $pdo->query(
                'SELECT id FROM promociones ORDER BY prioridad DESC, fecha_inicio ASC, id ASC FOR UPDATE'
            )->fetchAll(\PDO::FETCH_COLUMN));

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

            $total = count($ids);
            $stmt = $pdo->prepare('UPDATE promociones SET prioridad = :prioridad WHERE id = :id');
            foreach ($ids as $indice => $idPromocion) {
                $stmt->execute(['prioridad' => min(100, $total - $indice), 'id' => $idPromocion]);   // 100 = tope que valida el formulario
            }

            $pdo->commit();

            return ['resultado' => 'movida', 'desde' => $posicion + 1, 'hasta' => $destino + 1];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
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
                (nombre, descripcion, imagen, tipo, valor, categoria_id, producto_id,
                 fecha_inicio, fecha_fin, dias_semana, hora_inicio, hora_fin,
                 combinable_con_lealtad, prioridad, activo)
             VALUES
                (:nombre, :descripcion, :imagen, :tipo, :valor, :categoria_id, :producto_id,
                 :fecha_inicio, :fecha_fin, :dias_semana, :hora_inicio, :hora_fin,
                 :combinable_con_lealtad, :prioridad, :activo)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE promociones SET
                nombre = :nombre, descripcion = :descripcion, imagen = :imagen, tipo = :tipo, valor = :valor,
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

        // Si la promoción apunta a una categoría o producto específico,
        // ese objetivo también debe seguir activo — si se desactivó el
        // producto, la promoción no debe seguir anunciándolo como vigente
        // (antes no se validaba esto).
        $filas = Database::conexion()->query(
            "SELECT pr.*, c.nombre AS categoria_nombre, p.nombre AS producto_nombre
             FROM promociones pr
             LEFT JOIN categorias c ON c.id = pr.categoria_id
             LEFT JOIN productos p ON p.id = pr.producto_id
             WHERE pr.activo = 1 AND CURDATE() BETWEEN pr.fecha_inicio AND pr.fecha_fin
               AND (pr.categoria_id IS NULL OR c.activo = 1)
               AND (pr.producto_id IS NULL OR p.activo = 1)
             ORDER BY pr.prioridad DESC, pr.fecha_inicio ASC, pr.id ASC"
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

    /**
     * Promociones vigentes ahora mismo que además están marcadas "Se puede
     * combinar con el programa de lealtad". Reutiliza listarVigentesAhora()
     * (mismo cálculo de vigencia/día/hora, sin duplicarlo) y solo filtra por
     * ese campo — para mostrarlas como aviso en la cuenta del cliente
     * (docs/arquitectura.md §4.19). No cambia el conteo de cafés ni las
     * reglas de canje: es puramente informativo.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarVigentesCombinablesConLealtad(): array
    {
        return array_values(array_filter(
            $this->listarVigentesAhora(),
            fn (array $promo): bool => (int) $promo['combinable_con_lealtad'] === 1
        ));
    }

    public function contarPorCategoria(int $categoriaId): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM promociones WHERE categoria_id = :id');
        $stmt->execute(['id' => $categoriaId]);

        return (int) $stmt->fetchColumn();
    }

    public function contarVigentesHoy(): int
    {
        return (int) Database::conexion()->query(
            'SELECT COUNT(*) FROM promociones WHERE activo = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin'
        )->fetchColumn();
    }
}
