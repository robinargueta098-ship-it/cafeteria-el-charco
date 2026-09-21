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

        // Dos parámetros distintos (:nombre / :telefono) con el mismo valor: con
        // ATTR_EMULATE_PREPARES=false PDO no permite repetir un mismo nombre de
        // parámetro en la sentencia (error "Invalid parameter number" → 500).
        $stmt = Database::conexion()->prepare(
            "SELECT * FROM clientes
             WHERE nombre LIKE :por_nombre ESCAPE '\\\\' OR telefono LIKE :por_telefono ESCAPE '\\\\'
             ORDER BY nombre ASC LIMIT 50"
        );
        $stmt->execute(['por_nombre' => $comodin, 'por_telefono' => $comodin]);

        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Deja el código personal como se guarda: mayúsculas y "CL-" + 10
     * caracteres. Acepta "cl abcdefghjk", "CL-ABCDEFGHJK", etc. Devuelve ''
     * si no tiene esa forma (los códigos de recompensa tienen 8, no 10).
     */
    public static function normalizarCodigoQr(string $entrada): string
    {
        $limpio = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($entrada));

        return preg_match('/^CL[A-Z0-9]{10}$/', $limpio) === 1 ? 'CL-' . substr($limpio, 2) : '';
    }

    public function buscarPorCodigoQr(string $codigoQr): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM clientes WHERE codigo_qr = :codigo_qr');
        $stmt->execute(['codigo_qr' => $codigoQr]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Código personal del cliente (el que va en su QR para sumar cafés). Se
     * crea la primera vez que se pide y desde entonces es siempre el mismo.
     * Es aleatorio (random_int) y NO se deriva del teléfono ni del id, así
     * nadie puede adivinar el de otra persona. La garantía de que no se
     * repite es el UNIQUE de la columna: ante una colisión se genera otro.
     */
    public function obtenerCodigoQr(int $id): string
    {
        $pdo = Database::conexion();
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        for ($intento = 1; $intento <= 5; $intento++) {
            $stmt = $pdo->prepare('SELECT codigo_qr FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetchColumn();

            if (is_string($actual) && $actual !== '') {
                return $actual;
            }

            $codigo = 'CL-';
            for ($i = 0; $i < 10; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            try {
                // "AND codigo_qr IS NULL": si otra petición lo asignó primero, no se pisa.
                $pdo->prepare('UPDATE clientes SET codigo_qr = :codigo_qr WHERE id = :id AND codigo_qr IS NULL')
                    ->execute(['codigo_qr' => $codigo, 'id' => $id]);
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
                // Colisión con el código de otro cliente: se reintenta con uno nuevo.
            }
        }

        throw new \RuntimeException("No se pudo asignar un código QR al cliente {$id}.");
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
