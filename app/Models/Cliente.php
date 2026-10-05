<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Cliente
{
    /** Cliente + cuántas recompensas tiene disponibles (para mostrar el contador de la tarjeta). */
    private const SELECT_CON_RECOMPENSAS =
        "SELECT c.*, (SELECT COUNT(*) FROM recompensas r WHERE r.cliente_id = c.id AND r.estado = 'disponible') AS recompensas_disponibles FROM clientes c";

    /**
     * Contador "X / Y" de la tarjeta de lealtad. Al completar el umbral el saldo real vuelve
     * a 0 (empieza la siguiente tarjeta) aunque las recompensas sigan sin canjear; mostrar
     * "0 / 10" en ese momento confunde. Mientras el saldo sea 0 y haya N recompensas
     * disponibles se muestra la tarjeta llena de las N completadas (10/10, 20/20, 30/30…).
     * Si además hay un saldo parcial (ej. 25 cafés = 2 recompensas + 5) se muestra ese
     * saldo normal (5 / 10) y solo se avisa cuántas recompensas hay por canjear.
     * Mismo criterio que "Mi cuenta" (views/public/cuenta/panel.php).
     *
     * @param array<string,mixed> $cliente fila obtenida con recompensas_disponibles
     * @return array{mostrado:int,total:int,completa:bool,disponibles:int}
     */
    public static function progresoTarjeta(array $cliente, int $umbral): array
    {
        $saldo = (int) $cliente['cafes_acumulados'];
        $disponibles = (int) ($cliente['recompensas_disponibles'] ?? 0);
        $completa = $saldo === 0 && $disponibles > 0;

        return [
            'mostrado'    => $completa ? $disponibles * $umbral : $saldo,
            'total'       => $completa ? $disponibles * $umbral : $umbral,
            'completa'    => $completa,
            'disponibles' => $disponibles,
        ];
    }

    /** "1 café gratis disponible" / "2 cafés gratis disponibles". */
    public static function etiquetaGratis(int $n): string
    {
        return $n === 1 ? '1 café gratis disponible' : $n . ' cafés gratis disponibles';
    }

    /** El teléfono de un cliente son exactamente 8 dígitos (números de Guatemala, sin código de país). */
    public const DIGITOS_TELEFONO = 8;

    public static function telefonoValido(string $telefono): bool
    {
        return preg_match('/^\d{' . self::DIGITOS_TELEFONO . '}$/', $telefono) === 1;
    }

    public const MENSAJE_TELEFONO = 'El teléfono debe tener exactamente 8 dígitos (solo números, sin espacios ni guiones).';

    /**
     * Cuántos registros de lealtad cuelgan de un cliente (para avisar antes de borrarlo).
     *
     * @return array{movimientos:int,recompensas:int,cupones:int}
     */
    public function contarHistorial(int $id): array
    {
        $pdo = Database::conexion();
        $contar = function (string $tabla) use ($pdo, $id): int {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} WHERE cliente_id = :id");
            $stmt->execute(['id' => $id]);
            return (int) $stmt->fetchColumn();
        };

        return [
            'movimientos' => $contar('lealtad_movimientos'),
            'recompensas' => $contar('recompensas'),
            'cupones'     => $contar('cupones_promocion'),
        ];
    }

    /**
     * Borra al cliente y TODO lo que depende de él (cupones, recompensas y movimientos de
     * lealtad), en una sola transacción: o se borra todo o no se borra nada. El orden respeta
     * las llaves foráneas (las recompensas apuntan a movimientos, y estos al cliente).
     */
    public function eliminarConHistorial(int $id): void
    {
        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();
            foreach (['cupones_promocion', 'recompensas', 'lealtad_movimientos'] as $tabla) {
                $pdo->prepare("DELETE FROM {$tabla} WHERE cliente_id = :id")->execute(['id' => $id]);
            }
            $pdo->prepare('DELETE FROM clientes WHERE id = :id')->execute(['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

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
                ->query(self::SELECT_CON_RECOMPENSAS . ' ORDER BY c.nombre ASC LIMIT 50')
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
            self::SELECT_CON_RECOMPENSAS . "
             WHERE c.nombre LIKE :por_nombre ESCAPE '\\\\' OR c.telefono LIKE :por_telefono ESCAPE '\\\\'
             ORDER BY c.nombre ASC LIMIT 50"
        );
        $stmt->execute(['por_nombre' => $comodin, 'por_telefono' => $comodin]);

        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT_CON_RECOMPENSAS . ' WHERE c.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Deja el código personal como se compara al buscarlo: solo mayúsculas
     * y caracteres alfanuméricos. Acepta el formato nuevo, 8 caracteres sin
     * guion (ej. "G5S3WYHX"), y el viejo, "CL-" + 10 caracteres, de antes
     * de acortarlo (docs/arquitectura.md §4.15) — no hace falta distinguirlos
     * aquí: buscarPorCodigoQr() ignora guiones al comparar.
     */
    public static function normalizarCodigoQr(string $entrada): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($entrada));
    }

    /** Compara ignorando guiones — ver normalizarCodigoQr() para el porqué. */
    public function buscarPorCodigoQr(string $codigoQr): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT_CON_RECOMPENSAS . " WHERE REPLACE(c.codigo_qr, '-', '') = :codigo_qr");
        $stmt->execute(['codigo_qr' => $codigoQr]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Código personal del cliente (el que va en su QR para sumar cafés). Se
     * crea la primera vez que se pide y desde entonces es siempre el mismo.
     * Es aleatorio (random_int) y NO se deriva del teléfono ni del id, así
     * nadie puede adivinar el de otra persona. La garantía de que no se
     * repite es el UNIQUE de la columna: ante una colisión se genera otro.
     *
     * 8 caracteres, sin guion ni prefijo, mismo alfabeto sin ambigüedades
     * que Recompensa::nuevoCodigoAleatorio() (sin 0/O/1/I/L) — acortado en
     * docs/arquitectura.md §4.15; antes era "CL-" + 10 caracteres.
     */
    public function obtenerCodigoQr(int $id): string
    {
        $pdo = Database::conexion();

        for ($intento = 1; $intento <= 5; $intento++) {
            $stmt = $pdo->prepare('SELECT codigo_qr FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetchColumn();

            if (is_string($actual) && $actual !== '') {
                return $actual;
            }

            $codigo = self::nuevoCodigoQrAleatorio();

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

    /**
     * Reemplaza el código personal del cliente por uno nuevo, aunque ya
     * tuviera uno — a diferencia de obtenerCodigoQr(), que conserva el que
     * ya existía. El código anterior deja de servir de inmediato (esta es
     * la única forma en que un código personal "deja de funcionar": no hay
     * historial de códigos viejos como con las recompensas, porque el
     * cliente solo tiene uno vigente a la vez). Pensado para cuando el
     * cliente lo comparte por error o pide uno más corto.
     */
    public function regenerarCodigoQr(int $id): string
    {
        $pdo = Database::conexion();

        for ($intento = 1; $intento <= 5; $intento++) {
            $codigo = self::nuevoCodigoQrAleatorio();

            try {
                $stmt = $pdo->prepare('UPDATE clientes SET codigo_qr = :codigo_qr WHERE id = :id');
                $stmt->execute(['codigo_qr' => $codigo, 'id' => $id]);

                if ($stmt->rowCount() === 1) {
                    return $codigo;
                }

                throw new \RuntimeException("Cliente {$id} no encontrado.");
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
                // Colisión con el código de otro cliente: se reintenta con uno nuevo.
            }
        }

        throw new \RuntimeException("No se pudo regenerar el código QR del cliente {$id}.");
    }

    private static function nuevoCodigoQrAleatorio(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $maximo = strlen($alfabeto) - 1;

        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= $alfabeto[random_int(0, $maximo)];
        }

        return $codigo;
    }

    public function buscarPorTelefono(string $telefono): ?array
    {
        $stmt = Database::conexion()->prepare(self::SELECT_CON_RECOMPENSAS . ' WHERE c.telefono = :telefono');
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

    /**
     * Un PIN temporal (restablecido por el personal, en persona) se guarda en la misma columna
     * `pin_hash`, también hasheado, con este prefijo delante. Así "es temporal" y "cuál es el
     * hash" viajan en un solo valor que se cambia de forma atómica, sin agregar columnas.
     */
    private const PREFIJO_PIN_TEMPORAL = 'temporal:';

    public static function pinEsTemporal(array $registro): bool
    {
        return str_starts_with((string) ($registro['pin_hash'] ?? ''), self::PREFIJO_PIN_TEMPORAL);
    }

    /** El hash bcrypt a comparar con password_verify(), sin la marca de "temporal". */
    public static function hashDelPin(array $registro): string
    {
        $valor = (string) ($registro['pin_hash'] ?? '');

        return self::pinEsTemporal($registro) ? substr($valor, strlen(self::PREFIJO_PIN_TEMPORAL)) : $valor;
    }

    /**
     * Huella del PIN vigente. Se guarda en la sesión del cliente al entrar; si el PIN cambia
     * (restablecimiento o cambio forzado), la huella ya no coincide y esas sesiones dejan de valer.
     */
    public static function huellaPin(array $registro): string
    {
        return substr(hash('sha256', (string) ($registro['pin_hash'] ?? '')), 0, 32);
    }

    /** Deja un PIN temporal (ya hasheado) y limpia los contadores de intentos. */
    public function establecerPinTemporal(int $id, string $pinHash): void
    {
        $this->establecerPin($id, self::PREFIJO_PIN_TEMPORAL . $pinHash);
    }

    public function establecerPin(int $id, string $pinHash): void
    {
        Database::conexion()->prepare(
            'UPDATE clientes
             SET pin_hash = :hash, pin_intentos_fallidos = 0, pin_bloqueado_hasta = NULL
             WHERE id = :id'
        )->execute(['hash' => $pinHash, 'id' => $id]);
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
