<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\OrdenLista;

/**
 * Fotos del fondo automático de la portada (hero). Tabla propia,
 * separada de `galeria` a propósito: son cosas distintas y borrar una
 * foto de un lado nunca toca la del otro.
 */
class PortadaImagen
{
    /** @return array<int,array<string,mixed>> En el orden en que se muestran. */
    public function listar(): array
    {
        return Database::conexion()->query('SELECT * FROM portada_imagenes ORDER BY orden ASC, id ASC')->fetchAll();
    }

    public function contar(): int
    {
        return (int) Database::conexion()->query('SELECT COUNT(*) FROM portada_imagenes')->fetchColumn();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM portada_imagenes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Agrega al final de la lista. @return int id de la fila nueva */
    public function crear(string $imagen): int
    {
        Database::conexion()->prepare(
            'INSERT INTO portada_imagenes (imagen, orden) VALUES (:imagen, :orden)'
        )->execute(['imagen' => $imagen, 'orden' => OrdenLista::siguiente('portada_imagenes')]);

        return (int) Database::conexion()->lastInsertId();
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM portada_imagenes WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return array{resultado:string,desde?:int,hasta?:int} */
    public function mover(int $id, string $direccion): array
    {
        return OrdenLista::mover('portada_imagenes', $id, $direccion);
    }
}
