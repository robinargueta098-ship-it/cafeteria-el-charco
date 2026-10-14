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
    /** Campos que cambian solos o no dicen nada: nunca cuentan como "un cambio". */
    private const CAMPOS_IGNORADOS = ['creado_en', 'actualizado_en', 'updated_at', 'created_at'];

    /**
     * Qué NO se guarda (para que la bitácora sea útil y no un registro de cada clic):
     *  - los inicios de sesión (los intentos fallidos ya quedan en `intentos_login`, que es lo que sirve contra abusos);
     *  - los movimientos ▲▼ de orden (solo cambian una posición);
     *  - las ediciones que no cambiaron nada (se guardó el formulario sin tocar nada).
     * Y en las ediciones se guardan SOLO los campos que cambiaron (antes y después), no la fila completa.
     *
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
        if ($accion === 'login') {
            return;
        }

        if ($accion === 'editar' && is_array($antes) && is_array($despues)) {
            if (array_keys($despues) === ['posicion']) {
                return;   // mover una posición ▲▼
            }

            [$antes, $despues] = self::soloCambios($antes, $despues);
            if ($despues === []) {
                return;   // no cambió nada
            }
        }

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

    /**
     * De una edición, deja solo los campos de $despues cuyo valor realmente cambió respecto de $antes.
     * (null, '' y "0.0"/"0" se comparan por su valor, no por cómo los entrega la base de datos.)
     *
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private static function soloCambios(array $antes, array $despues): array
    {
        $a = [];
        $d = [];
        foreach ($despues as $campo => $nuevo) {
            if (in_array($campo, self::CAMPOS_IGNORADOS, true)) {
                continue;
            }
            $viejo = $antes[$campo] ?? null;
            if (!self::mismoValor($viejo, $nuevo)) {
                $a[$campo] = $viejo;
                $d[$campo] = $nuevo;
            }
        }

        return [$a, $d];
    }

    private static function mismoValor(mixed $x, mixed $y): bool
    {
        if (is_array($x) || is_array($y)) {
            return json_encode($x) === json_encode($y);
        }
        $x = $x === null ? '' : (string) $x;
        $y = $y === null ? '' : (string) $y;
        if ($x === $y) {
            return true;
        }

        return is_numeric($x) && is_numeric($y) && (float) $x === (float) $y;
    }
}
