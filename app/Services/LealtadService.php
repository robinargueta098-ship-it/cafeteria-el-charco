<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Configuracion;
use Throwable;

/**
 * Toda la lógica del programa de lealtad pasa por aquí. Regla explícita
 * del negocio: el contador `clientes.cafes_acumulados` NUNCA se edita
 * directamente — cada cambio se hace en la misma transacción que su
 * movimiento en `lealtad_movimientos`, para que el saldo siempre sea
 * reconstruible desde el historial (docs/arquitectura.md §4.1-4.2).
 */
class LealtadService
{
    public static function obtenerUmbral(): int
    {
        $valor = (new Configuracion())->obtenerTodas()['umbral_lealtad'] ?? '10';
        $umbral = (int) $valor;

        return $umbral > 0 ? $umbral : 10;
    }

    /**
     * Registra la compra de $cantidad cafés para un cliente.
     * @return array{ok:bool,error?:string}
     */
    public static function registrarConsumo(int $clienteId, int $cantidad, int $usuarioId, ?string $nota = null): array
    {
        if ($cantidad <= 0) {
            return ['ok' => false, 'error' => 'La cantidad debe ser mayor que cero.'];
        }

        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            // FOR UPDATE bloquea la fila hasta el commit: si dos cajeros
            // registran al mismo cliente casi a la vez, el segundo espera
            // en vez de pisar el cambio del primero (evita "lost update").
            $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $clienteId]);
            if ($stmt->fetch() === false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cliente no encontrado.'];
            }

            $pdo->prepare(
                'INSERT INTO lealtad_movimientos (cliente_id, tipo, cantidad, usuario_id, nota)
                 VALUES (:cliente_id, "compra", :cantidad, :usuario_id, :nota)'
            )->execute([
                'cliente_id' => $clienteId,
                'cantidad'   => $cantidad,
                'usuario_id' => $usuarioId,
                'nota'       => $nota,
            ]);

            $pdo->prepare(
                'UPDATE clientes SET cafes_acumulados = cafes_acumulados + :cantidad WHERE id = :id'
            )->execute(['cantidad' => $cantidad, 'id' => $clienteId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            \App\Core\Logger::error('Error registrando consumo de lealtad: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo registrar el consumo.'];
        }

        AuditoriaService::registrar('lealtad_movimientos', $clienteId, 'consumo', null, [
            'cliente_id' => $clienteId, 'cantidad' => $cantidad,
        ]);

        return ['ok' => true];
    }

    /**
     * Canjea la recompensa (un café gratis) de un cliente, siempre por
     * exactamente el umbral vigente — nunca un monto libre.
     * @return array{ok:bool,error?:string}
     */
    public static function canjear(int $clienteId, int $productoId, int $usuarioId): array
    {
        $umbral = self::obtenerUmbral();
        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT cafes_acumulados FROM clientes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $clienteId]);
            $cliente = $stmt->fetch();

            if ($cliente === false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cliente no encontrado.'];
            }

            if ((int) $cliente['cafes_acumulados'] < $umbral) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "El cliente todavía no acumula los {$umbral} cafés necesarios."];
            }

            $stmtProducto = $pdo->prepare('SELECT id FROM productos WHERE id = :id AND activo = 1');
            $stmtProducto->execute(['id' => $productoId]);
            if ($stmtProducto->fetch() === false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Selecciona un producto activo del menú.'];
            }

            $pdo->prepare(
                'INSERT INTO lealtad_movimientos (cliente_id, tipo, cantidad, producto_canjeado_id, usuario_id)
                 VALUES (:cliente_id, "canje", :cantidad, :producto_id, :usuario_id)'
            )->execute([
                'cliente_id'  => $clienteId,
                'cantidad'    => -$umbral,
                'producto_id' => $productoId,
                'usuario_id'  => $usuarioId,
            ]);

            $pdo->prepare(
                'UPDATE clientes SET cafes_acumulados = cafes_acumulados - :umbral WHERE id = :id'
            )->execute(['umbral' => $umbral, 'id' => $clienteId]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            \App\Core\Logger::error('Error canjeando recompensa de lealtad: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo registrar el canje.'];
        }

        AuditoriaService::registrar('lealtad_movimientos', $clienteId, 'canje', null, [
            'cliente_id' => $clienteId, 'producto_id' => $productoId, 'umbral_canjeado' => $umbral,
        ]);

        return ['ok' => true];
    }
}
