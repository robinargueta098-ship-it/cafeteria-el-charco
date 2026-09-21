<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Una fila por cada café gratis generado al cruzar el umbral de
 * lealtad. El código es lo único que identifica una recompensa para
 * canjearla — nunca se busca ni se canjea por cliente_id directo desde
 * la pantalla de canje (docs/arquitectura.md: canje por código).
 */
class Recompensa
{
    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM recompensas WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Búsqueda exacta por código, sin bloqueo — para mostrar detalle antes de confirmar el canje. */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono
             FROM recompensas r JOIN clientes c ON c.id = r.cliente_id
             WHERE r.codigo = :codigo'
        );
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    public function codigoExiste(string $codigo): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM recompensas WHERE codigo = :codigo');
        $stmt->execute(['codigo' => $codigo]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int,array<string,mixed>> Recompensas de un cliente, más recientes primero. */
    public function listarPorCliente(int $clienteId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT r.*, p.nombre AS producto_nombre
             FROM recompensas r
             LEFT JOIN productos p ON p.id = r.producto_canjeado_id
             WHERE r.cliente_id = :cliente_id
             ORDER BY r.fecha_generada DESC'
        );
        $stmt->execute(['cliente_id' => $clienteId]);

        return $stmt->fetchAll();
    }

    /**
     * @param array{estado?:string} $filtros
     * @return array<int,array<string,mixed>>
     */
    public function listarTodas(array $filtros = []): array
    {
        $sql = 'SELECT r.*, c.nombre AS cliente_nombre, c.telefono AS cliente_telefono,
                       p.nombre AS producto_nombre, u.nombre AS usuario_canjeo_nombre
                FROM recompensas r
                JOIN clientes c ON c.id = r.cliente_id
                LEFT JOIN productos p ON p.id = r.producto_canjeado_id
                LEFT JOIN usuarios u ON u.id = r.usuario_canjeo_id';
        $parametros = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' WHERE r.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }

        $sql .= ' ORDER BY r.fecha_generada DESC LIMIT 200';

        $stmt = Database::conexion()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO recompensas (cliente_id, codigo, movimiento_generador_id, fecha_vencimiento)
             VALUES (:cliente_id, :codigo, :movimiento_generador_id, :fecha_vencimiento)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    /**
     * Marca la recompensa como usada (con fecha de canje). El
     * `AND estado = 'disponible'` hace la operación atómica: aunque el
     * llamador se equivocara y no tuviera el candado, una recompensa
     * que ya no está disponible NO se vuelve a canjear.
     *
     * @return bool true si se marcó; false si ya no estaba disponible
     */
    public function marcarCanjeada(int $id, int $productoId, int $usuarioId, int $movimientoCanjeId): bool
    {
        $stmt = Database::conexion()->prepare(
            "UPDATE recompensas
             SET estado = 'canjeada', producto_canjeado_id = :producto_id,
                 usuario_canjeo_id = :usuario_id, fecha_canjeada = NOW(),
                 movimiento_canje_id = :movimiento_id
             WHERE id = :id AND estado = 'disponible'"
        );
        $stmt->execute([
            'producto_id'   => $productoId,
            'usuario_id'    => $usuarioId,
            'movimiento_id' => $movimientoCanjeId,
            'id'            => $id,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function marcarVencida(int $id): void
    {
        Database::conexion()->prepare("UPDATE recompensas SET estado = 'vencida' WHERE id = :id")
            ->execute(['id' => $id]);
    }

    public function marcarCancelada(int $id, int $usuarioId, string $motivo, int $movimientoCancelacionId): void
    {
        Database::conexion()->prepare(
            'UPDATE recompensas
             SET estado = "cancelada", usuario_cancelacion_id = :usuario_id,
                 fecha_cancelacion = NOW(), motivo_cancelacion = :motivo,
                 movimiento_cancelacion_id = :movimiento_id
             WHERE id = :id'
        )->execute([
            'usuario_id'    => $usuarioId,
            'motivo'        => $motivo,
            'movimiento_id' => $movimientoCancelacionId,
            'id'            => $id,
        ]);
    }

    /**
     * Bloquea la fila (SELECT ... FOR UPDATE) dentro de una transacción
     * ya abierta por el llamador — para canjear/cancelar sin condición
     * de carrera. Debe usarse siempre dentro de una transacción.
     */
    public function buscarPorCodigoConBloqueo(string $codigo): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT * FROM recompensas WHERE codigo = :codigo FOR UPDATE'
        );
        $stmt->execute(['codigo' => $codigo]);

        return $stmt->fetch() ?: null;
    }

    /** Igual que buscarPorCodigoConBloqueo pero por ID (para cancelar). Usar dentro de una transacción. */
    public function buscarPorIdConBloqueo(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM recompensas WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * SOLO PARA PRUEBAS: si se asigna, reemplaza por completo a
     * generarCodigoUnico() (permite forzar una colisión y comprobar que
     * el reintento funciona). En producción siempre es null.
     *
     * @var \Closure():string|null
     */
    public static ?\Closure $generadorDeCodigo = null;

    /**
     * Devuelve un código que, en este momento, no existe en la base.
     *
     * OJO: esta comprobación NO basta por sí sola — entre "comprobar" y
     * "guardar" otra petición puede generar el mismo código. La garantía
     * real es el UNIQUE de la columna; quien inserte debe capturar la
     * colisión con esViolacionDeUnicidad() y volver a pedir otro (ver
     * LealtadService::crearRecompensaConCodigoUnico()).
     */
    public function generarCodigoUnico(): string
    {
        if (self::$generadorDeCodigo !== null) {
            return (self::$generadorDeCodigo)();
        }

        do {
            $codigo = self::nuevoCodigoAleatorio();
        } while ($this->codigoExiste($codigo));

        return $codigo;
    }

    /**
     * 8 caracteres elegidos con random_int() (aleatorio criptográfico,
     * no predecible ni consecutivo) de un alfabeto de 31 símbolos sin
     * ambigüedades (sin 0/O, 1/I/L): 31^8 ≈ 8,5 × 10^11 combinaciones.
     * Formato XXXX-XXXX (9 caracteres; el guion es solo presentación).
     */
    public static function nuevoCodigoAleatorio(): string
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $maximo = strlen($alfabeto) - 1;

        $codigo = '';
        for ($i = 0; $i < 8; $i++) {
            $codigo .= $alfabeto[random_int(0, $maximo)];
        }

        return substr($codigo, 0, 4) . '-' . substr($codigo, 4);
    }

    /**
     * Deja el código tal como se guarda: mayúsculas y con guion.
     * Acepta "abcd efgh", "abcd-efgh" o "ABCDEFGH" (lo que teclee el
     * cajero o copie el cliente). Si no tiene 8 caracteres válidos
     * devuelve lo que quede, que simplemente no coincidirá con nada.
     */
    public static function normalizarCodigo(string $entrada): string
    {
        $limpio = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($entrada));

        return strlen($limpio) === 8
            ? substr($limpio, 0, 4) . '-' . substr($limpio, 4)
            : $limpio;
    }

    /** ¿La excepción es una violación de UNIQUE (MySQL/MariaDB error 1062)? */
    public static function esViolacionDeUnicidad(\PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
