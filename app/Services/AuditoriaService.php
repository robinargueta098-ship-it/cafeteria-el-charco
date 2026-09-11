<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Registro centralizado en `bitacora_auditoria`. Se llama desde cada
 * controlador tras una acción exitosa, para que ningún módulo nuevo
 * pueda "olvidar" auditar (docs/arquitectura.md §4 y §7).
 *
 * Nunca debe poder tumbar la operación que está auditando: si algo
 * sale mal aquí (ej. datos con una codificación rara que rompe el
 * JSON), se registra en el log y se continúa — la auditoría es un
 * registro secundario, no la razón de ser de la acción del usuario.
 */
class AuditoriaService
{
    /**
     * @param array<string,mixed>|null $antes
     * @param array<string,mixed>|null $despues
     */
    public static function registrar(
        string $tabla,
        int $registroId,
        string $accion,
        ?array $antes = null,
        ?array $despues = null
    ): void {
        try {
            $usuario = Auth::usuario();

            // JSON_INVALID_UTF8_SUBSTITUTE evita que json_encode() falle
            // silenciosamente (devolviendo false) si algún valor trae
            // bytes que no son UTF-8 válido — los reemplaza en vez de
            // abortar la codificación.
            $banderas = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

            Database::conexion()->prepare(
                'INSERT INTO bitacora_auditoria
                    (tabla_afectada, registro_id, accion, usuario_id, valores_antes, valores_despues, ip)
                 VALUES (:tabla, :registro_id, :accion, :usuario_id, :antes, :despues, :ip)'
            )->execute([
                'tabla'       => $tabla,
                'registro_id' => $registroId,
                'accion'      => $accion,
                'usuario_id'  => $usuario['id'] ?? null,
                'antes'       => $antes !== null ? json_encode($antes, $banderas) : null,
                'despues'     => $despues !== null ? json_encode($despues, $banderas) : null,
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            Logger::error('No se pudo registrar en bitacora_auditoria: ' . $e->getMessage());
        }
    }
}
