<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Rol
{
    /** @return array<int,array<string,mixed>> */
    public function listarTodos(): array
    {
        return Database::conexion()->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();
    }

    public function existe(int $id): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM roles WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
