<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Cliente
{
    /** @return array<int,array<string,mixed>> Búsqueda por nombre o teléfono (parcial). */
    public function buscar(string $termino): array
    {
        $termino = trim($termino);

        if ($termino === '') {
            return Database::conexion()
                ->query('SELECT * FROM clientes ORDER BY nombre ASC LIMIT 50')
                ->fetchAll();
        }

        // Se escapan los comodines propios de LIKE para que un cliente
        // que de casualidad tenga "%" o "_" en el nombre no altere la
        // búsqueda de otros (no es un riesgo de seguridad vía PDO
        // preparado, solo de resultados incorrectos).
        $comodin = '%' . str_replace(['%', '_'], ['\%', '\_'], $termino) . '%';

        $stmt = Database::conexion()->prepare(
            "SELECT * FROM clientes
             WHERE nombre LIKE :termino ESCAPE '\\\\' OR telefono LIKE :termino ESCAPE '\\\\'
             ORDER BY nombre ASC LIMIT 50"
        );
        $stmt->execute(['termino' => $comodin]);

        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function buscarPorTelefono(string $telefono): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM clientes WHERE telefono = :telefono');
        $stmt->execute(['telefono' => $telefono]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO clientes (nombre, telefono, email, notas) VALUES (:nombre, :telefono, :email, :notas)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function contarConRecompensaDisponible(int $umbral): int
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM clientes WHERE cafes_acumulados >= :umbral');
        $stmt->execute(['umbral' => $umbral]);

        return (int) $stmt->fetchColumn();
    }

    public function contarTotal(): int
    {
        return (int) Database::conexion()->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE clientes SET nombre = :nombre, telefono = :telefono, email = :email, notas = :notas WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }
}
