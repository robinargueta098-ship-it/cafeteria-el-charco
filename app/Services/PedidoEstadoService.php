<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Máquina de estados de los pedidos. Se valida SIEMPRE aquí, en el servidor (la interfaz solo oculta botones).
 *
 *   pendiente  → confirmado | cancelado          (el cliente no confirmó el envío; el personal ya vio su WhatsApp)
 *   enviado    → confirmado
 *   confirmado → entregado                       (guarda entregado_en, hora de Guatemala)
 *   enviado | confirmado → cancelado             (motivo obligatorio)
 *   entregado  → anulado                         (solo Administrador; motivo obligatorio)
 *
 * Ningún otro salto es válido. El cambio es atómico y seguro ante dos usuarios a la vez:
 * UPDATE ... WHERE id = ? AND estado = <estado que el usuario vio>, y si no afecta ninguna fila, el pedido ya cambió.
 * Compatible con PHP 7.4.
 */
class PedidoEstadoService
{
    public const MOTIVO_MAX = 255;

    /** estado actual => [estado nuevo => ['motivo' => bool, 'solo_admin' => bool]] */
    private const TRANSICIONES = [
        // `pendiente` pasa a `enviado` solo cuando el cliente pulsa «Sí, ya lo envié» (PedidoService::confirmarEnvio).
        'pendiente'  => [
            'confirmado' => ['motivo' => false, 'solo_admin' => false],
            'cancelado'  => ['motivo' => true,  'solo_admin' => false],
        ],
        'enviado'    => [
            'confirmado' => ['motivo' => false, 'solo_admin' => false],
            'cancelado'  => ['motivo' => true,  'solo_admin' => false],
        ],
        'confirmado' => [
            'entregado'  => ['motivo' => false, 'solo_admin' => false],
            'cancelado'  => ['motivo' => true,  'solo_admin' => false],
        ],
        'entregado'  => [
            'anulado'    => ['motivo' => true,  'solo_admin' => true],
        ],
        'cancelado'  => [],
        'anulado'    => [],
    ];

    /**
     * Transiciones que este rol puede ejecutar desde un estado (para pintar solo los botones válidos).
     *
     * @return array<string,array{motivo:bool,solo_admin:bool}>
     */
    public static function disponibles(string $estadoActual, int $rolId): array
    {
        $r = [];
        foreach (isset(self::TRANSICIONES[$estadoActual]) ? self::TRANSICIONES[$estadoActual] : [] as $nuevo => $regla) {
            if ($regla['solo_admin'] && $rolId !== 1) {
                continue;
            }
            $r[$nuevo] = $regla;
        }

        return $r;
    }

    /**
     * @return array{ok:bool,error?:string,codigo?:string}  error = mensaje para el usuario
     */
    public static function cambiar(int $pedidoId, string $estadoVisto, string $nuevo, int $usuarioId, int $rolId, string $motivo): array
    {
        $motivo = trim($motivo);

        if (!isset(self::TRANSICIONES[$estadoVisto][$nuevo])) {
            return ['ok' => false, 'error' => 'Ese cambio de estado no está permitido.'];
        }
        $regla = self::TRANSICIONES[$estadoVisto][$nuevo];

        if ($regla['solo_admin'] && $rolId !== 1) {
            return ['ok' => false, 'error' => 'Solo un Administrador puede anular un pedido entregado.'];
        }
        if ($regla['motivo'] && $motivo === '') {
            return ['ok' => false, 'error' => 'Escribe el motivo: es obligatorio para este cambio.'];
        }
        if (mb_strlen($motivo) > self::MOTIVO_MAX) {
            return ['ok' => false, 'error' => 'El motivo no puede pasar de ' . self::MOTIVO_MAX . ' caracteres.'];
        }

        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $sql = 'UPDATE pedidos SET estado = :nuevo, actualizado_por = :usuario'
                . ($nuevo === 'entregado' ? ', entregado_en = NOW()' : '')
                . ' WHERE id = :id AND estado = :esperado';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['nuevo' => $nuevo, 'usuario' => $usuarioId, 'id' => $pedidoId, 'esperado' => $estadoVisto]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'El pedido ya cambió (otra persona lo actualizó o ya no existe). Revisa su estado actual.'];
            }

            $pdo->prepare(
                'INSERT INTO pedido_historial (pedido_id, estado_anterior, estado_nuevo, usuario_id, motivo)
                 VALUES (:pedido, :anterior, :nuevo, :usuario, :motivo)'
            )->execute([
                'pedido'   => $pedidoId,
                'anterior' => $estadoVisto,
                'nuevo'    => $nuevo,
                'usuario'  => $usuarioId,
                'motivo'   => $motivo !== '' ? $motivo : null,
            ]);

            $stmt = $pdo->prepare('SELECT codigo FROM pedidos WHERE id = :id');
            $stmt->execute(['id' => $pedidoId]);
            $codigo = (string) $stmt->fetchColumn();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('No se pudo cambiar el estado del pedido ' . $pedidoId . ': ' . $e->getMessage());

            return ['ok' => false, 'error' => 'No se pudo guardar el cambio. Inténtalo de nuevo.'];
        }

        $datos = ['estado' => $nuevo, 'codigo' => $codigo];
        if ($motivo !== '') {
            $datos['motivo'] = $motivo;
        }
        AuditoriaService::registrar('pedidos', $pedidoId, 'editar', ['estado' => $estadoVisto], $datos);

        return ['ok' => true, 'codigo' => $codigo];
    }
}
