<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Galeria
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()->query('SELECT * FROM galeria ORDER BY orden ASC, id ASC')->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Para el sitio público. */
    public function listarActivas(): array
    {
        return Database::conexion()->query('SELECT * FROM galeria WHERE activo = 1 ORDER BY orden ASC, id ASC')->fetchAll();
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

    public function contarActivas(): int
    {
        return (int) Database::conexion()->query('SELECT COUNT(*) FROM galeria WHERE activo = 1')->fetchColumn();
    }

    /** Posición para una foto nueva: al final de la galería. */
    public function siguienteOrden(): int
    {
        return (int) Database::conexion()->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM galeria')->fetchColumn();
    }

    /**
     * Intercambia la foto con su vecina ('subir' o 'bajar').
     *
     * Antes de intercambiar, renumera TODAS las fotos como 1..n según su
     * orden actual: así funciona aunque haya empates o huecos en `orden`
     * (ej. fotos viejas que quedaron todas en 0), en vez de "intercambiar"
     * dos números iguales y no lograr nada. Todo dentro de una transacción
     * con las filas bloqueadas, para que dos clics simultáneos no se pisen.
     *
     * @return array{resultado:string,desde?:int,hasta?:int} resultado: 'movida' | 'extremo' | 'no_existe'
     */
    public function mover(int $id, string $direccion): array
    {
        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $ids = array_map(
                'intval',
                $pdo->query('SELECT id FROM galeria ORDER BY orden ASC, id ASC FOR UPDATE')->fetchAll(\PDO::FETCH_COLUMN)
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

            $stmt = $pdo->prepare('UPDATE galeria SET orden = :orden WHERE id = :id');
            foreach ($ids as $indice => $idFoto) {
                $stmt->execute(['orden' => $indice + 1, 'id' => $idFoto]);
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
}
