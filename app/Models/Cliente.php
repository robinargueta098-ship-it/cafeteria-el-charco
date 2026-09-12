<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Cliente
{
    /**
     * Quita espacios y guiones para que "5555 1234", "5555-1234" y
     * "55551234" se traten como el mismo teléfono tanto si lo escribe
     * el cajero en el panel como si lo escribe el cliente en su
     * cuenta. Conserva un "+" inicial (código de país).
     */
    public static function normalizarTelefono(string $telefono): string
    {
        return (string) preg_replace('/[\s\-]/', '', trim($telefono));
    }

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

    /** Clientes con al menos una recompensa disponible para canjear (no clientes con saldo alto: eso ya no existe, se convierte solo). */
    public function contarConRecompensaDisponible(): int
    {
        return (int) Database::conexion()
            ->query("SELECT COUNT(DISTINCT cliente_id) FROM recompensas WHERE estado = 'disponible'")
            ->fetchColumn();
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

    // --- Cuenta simple del cliente (teléfono + PIN de 4 dígitos) ---

    public function establecerPin(int $id, string $pinHash): void
    {
        Database::conexion()->prepare(
            'UPDATE clientes
             SET pin_hash = :hash, pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL
             WHERE id = :id'
        )->execute(['hash' => $pinHash, 'id' => $id]);
    }

    public function registrarIntentoPinFallido(int $id, int $maxIntentos, int $minutosBloqueo): void
    {
        $pdo = Database::conexion();

        $pdo->prepare(
            'UPDATE clientes SET pin_intentos_fallidos = pin_intentos_fallidos + 1 WHERE id = :id'
        )->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT pin_intentos_fallidos FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $intentos = (int) $stmt->fetchColumn();

        if ($intentos >= $maxIntentos) {
            $pdo->prepare(
                'UPDATE clientes SET pin_bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :minutos MINUTE) WHERE id = :id'
            )->execute(['minutos' => $minutosBloqueo, 'id' => $id]);
        }
    }

    public function registrarAccesoClienteExitoso(int $id): void
    {
        Database::conexion()->prepare(
            'UPDATE clientes
             SET pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL, ultimo_acceso_cliente = NOW()
             WHERE id = :id'
        )->execute(['id' => $id]);
    }
}
