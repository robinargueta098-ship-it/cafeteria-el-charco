<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\OrdenLista;

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
        return OrdenLista::siguiente('galeria');
    }

    /**
     * Intercambia la foto con su vecina ('subir' o 'bajar'); la regla
     * (renumerar 1..n en transacción con filas bloqueadas) es compartida
     * con la portada, ver App\Core\OrdenLista.
     *
     * @return array{resultado:string,desde?:int,hasta?:int} resultado: 'movida' | 'extremo' | 'no_existe'
     */
    public function mover(int $id, string $direccion): array
    {
        return OrdenLista::mover('galeria', $id, $direccion);
    }
}
