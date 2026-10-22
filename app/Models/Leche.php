<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Leches que el cliente puede elegir en el carrito (Entera, Deslactosada, Almendras…). Cada una suma su `precio_extra`
 * al precio del producto. Se administran en el panel (Leches); el sitio y el servidor leen siempre de aquí.
 */
class Leche
{
    public const PRECIO_MAXIMO = 50.0;

    /** @return array<int,array<string,mixed>> */
    public function listarTodas(): array
    {
        return Database::conexion()->query('SELECT * FROM leches ORDER BY orden ASC, id ASC')->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listarActivas(): array
    {
        return Database::conexion()->query('SELECT * FROM leches WHERE activo = 1 ORDER BY orden ASC, id ASC')->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM leches WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** ¿Ya hay otra leche con este nombre? (sin distinguir mayúsculas ni acentos: la colación es _ci) */
    public function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM leches WHERE nombre = :nombre AND id <> :excluir');
        $stmt->execute(['nombre' => trim($nombre), 'excluir' => $excluirId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array{nombre:string,precio_extra:string,activo:int} $datos */
    public function crear(array $datos): int
    {
        $pdo = Database::conexion();
        $siguiente = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM leches')->fetchColumn();
        $pdo->prepare('INSERT INTO leches (nombre, precio_extra, activo, orden) VALUES (:nombre, :precio_extra, :activo, :orden)')
            ->execute($datos + ['orden' => $siguiente]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array{nombre:string,precio_extra:string,activo:int} $datos */
    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()
            ->prepare('UPDATE leches SET nombre = :nombre, precio_extra = :precio_extra, activo = :activo WHERE id = :id')
            ->execute($datos + ['id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM leches WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Sube o baja una posición (▲▼). Reasigna el orden de todas (1, 2, 3…) para que no queden empates.
     *
     * @return string 'movida' | 'extremo' | 'no_existe'
     */
    public function mover(int $id, string $direccion): string
    {
        $ids = array_map(function ($f) { return (int) $f['id']; }, $this->listarTodas());

        $posicion = array_search($id, $ids, true);
        if ($posicion === false) {
            return 'no_existe';
        }
        $destino = $direccion === 'subir' ? $posicion - 1 : $posicion + 1;
        if ($destino < 0 || $destino >= count($ids)) {
            return 'extremo';
        }

        $tmp = $ids[$posicion];
        $ids[$posicion] = $ids[$destino];
        $ids[$destino] = $tmp;

        $stmt = Database::conexion()->prepare('UPDATE leches SET orden = :orden WHERE id = :id');
        foreach ($ids as $i => $leId) {
            $stmt->execute(['orden' => $i + 1, 'id' => $leId]);
        }

        return 'movida';
    }
}
