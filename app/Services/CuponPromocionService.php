<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\CuponPromocion;
use App\Models\Promocion;
use App\Models\Recompensa;
use Throwable;

/**
 * Cupón de una promoción "combinable con lealtad" (docs/arquitectura.md
 * §4.20): el cliente lo reclama una sola vez desde su cuenta, el cajero
 * lo canjea por código/QR. Concepto aparte de LealtadService — no toca
 * `cafes_acumulados` ni genera movimientos de lealtad; el UNIQUE de
 * `cupones_promocion` (cliente_id, promocion_id) es la garantía real de
 * "una vez por cliente y por promoción", ante una posible condición de
 * carrera de dos clics.
 */
class CuponPromocionService
{
    /** Igual límite de reintentos que Recompensa/LealtadService ante una colisión de código. */
    private const MAX_INTENTOS_CODIGO = 10;

    /**
     * @return array{ok:bool,error?:string,cupon?:array<string,mixed>}
     */
    public static function reclamar(int $clienteId, int $promocionId): array
    {
        $promocion = (new Promocion())->buscarPorId($promocionId);
        if ($promocion === null || (int) $promocion['activo'] !== 1) {
            return ['ok' => false, 'error' => 'Esta promoción ya no está disponible.'];
        }

        if ((int) $promocion['combinable_con_lealtad'] !== 1) {
            return ['ok' => false, 'error' => 'Esta promoción no es reclamable desde Mi cuenta.'];
        }

        $hoy = date('Y-m-d');
        if ($hoy < $promocion['fecha_inicio'] || $hoy > $promocion['fecha_fin']) {
            return ['ok' => false, 'error' => 'Esta promoción no está vigente en este momento.'];
        }

        $cupones = new CuponPromocion();

        if ($cupones->buscarDeClientePorPromocion($clienteId, $promocionId) !== null) {
            return ['ok' => false, 'error' => 'Ya reclamaste el cupón de esta promoción.'];
        }

        $pdo = Database::conexion();

        for ($intento = 1; $intento <= self::MAX_INTENTOS_CODIGO; $intento++) {
            $codigo = $cupones->generarCodigoUnico();

            try {
                $pdo->beginTransaction();

                $id = $cupones->crear([
                    'codigo'       => $codigo,
                    'cliente_id'   => $clienteId,
                    'promocion_id' => $promocionId,
                ]);

                $pdo->commit();

                return ['ok' => true, 'cupon' => $cupones->buscarPorId($id)];
            } catch (\PDOException $e) {
                $pdo->rollBack();

                if (Recompensa::esViolacionDeUnicidad($e)) {
                    // Dos posibles UNIQUE: el código (colisión rarísima, se
                    // reintenta con otro) o (cliente_id, promocion_id) —
                    // significa que otro clic/pestaña ya reclamó este mismo
                    // cupón un instante antes; no es un error real.
                    $existente = $cupones->buscarDeClientePorPromocion($clienteId, $promocionId);
                    if ($existente !== null) {
                        return ['ok' => true, 'cupon' => $existente];
                    }

                    Logger::error("Colisión de código de cupón ({$codigo}) en el intento {$intento}; se genera otro.");
                    continue;
                }

                Logger::error('Error reclamando cupón: ' . $e->getMessage());
                return ['ok' => false, 'error' => 'No se pudo reclamar el cupón.'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Logger::error('Error reclamando cupón: ' . $e->getMessage());
                return ['ok' => false, 'error' => 'No se pudo reclamar el cupón.'];
            }
        }

        return ['ok' => false, 'error' => 'No se pudo generar un código único. Intenta de nuevo.'];
    }

    /**
     * Valida un código de cupón y, si todo está en regla, lo marca
     * canjeado. Mismo orden de verificación que
     * LealtadService::validarYCanjearRecompensa(): existencia, estado,
     * vencimiento (perezoso, según la fecha_fin de la promoción — el
     * cupón nunca se puede canjear después de esa fecha).
     *
     * @return array{ok:bool,error?:string,cliente_nombre?:string,promocion_nombre?:string}
     */
    public static function validarYCanjearCupon(string $codigo, int $usuarioId): array
    {
        $codigo = Recompensa::normalizarCodigo($codigo);
        if ($codigo === '') {
            return ['ok' => false, 'error' => 'Ingresa un código.'];
        }

        $pdo = Database::conexion();
        $cupones = new CuponPromocion();

        try {
            $pdo->beginTransaction();

            // FOR UPDATE: dos cajeros canjeando el mismo código a la vez —
            // el segundo espera y ya ve estado='canjeado'. Nunca doble canje.
            $cupon = $cupones->buscarPorCodigoConBloqueo($codigo);

            if ($cupon === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Ese código no existe. Verifica que esté bien escrito.'];
            }

            if ($cupon['estado'] === 'canjeado') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Este cupón ya fue canjeado el ' . $cupon['fecha_canje'] . '.'];
            }

            if ($cupon['estado'] === 'cancelado') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Este cupón fue cancelado y ya no es válido.'];
            }

            if ($cupon['estado'] === 'vencido') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Este cupón venció.'];
            }

            $promocion = (new Promocion())->buscarPorId((int) $cupon['promocion_id']);
            $fechaFin = $promocion['fecha_fin'] ?? null;

            // estado === 'disponible': revisar vencimiento contra la fecha de
            // fin de la promoción (perezoso — se marca en este momento si ya pasó).
            if ($fechaFin !== null && $fechaFin < date('Y-m-d')) {
                $cupones->marcarVencido((int) $cupon['id']);
                $pdo->commit();
                return ['ok' => false, 'error' => 'Este cupón venció el ' . $fechaFin . '.'];
            }

            if (!$cupones->marcarCanjeado((int) $cupon['id'], $usuarioId)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Este cupón ya no está disponible.'];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Error canjeando cupón: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo procesar el canje.'];
        }

        $cupon = $cupones->buscarPorCodigo($codigo);

        AuditoriaService::registrar('cupones_promocion', (int) $cupon['id'], 'canje',
            ['estado' => 'disponible'],
            ['estado' => 'canjeado', 'usuario_id' => $usuarioId]);

        return [
            'ok'               => true,
            'cliente_nombre'   => $cupon['cliente_nombre'],
            'promocion_nombre' => $cupon['promocion_nombre'],
        ];
    }

    /** Solo Administrador (se exige en el controlador). No devuelve nada: el cupón no está atado a cafés acumulados. */
    public static function cancelar(int $cuponId, int $usuarioId, string $motivo): array
    {
        if (trim($motivo) === '') {
            return ['ok' => false, 'error' => 'El motivo de la cancelación es obligatorio.'];
        }

        $cupones = new CuponPromocion();
        $cupon = $cupones->buscarPorId($cuponId);

        if ($cupon === null) {
            return ['ok' => false, 'error' => 'Cupón no encontrado.'];
        }

        if ($cupon['estado'] !== 'disponible') {
            return ['ok' => false, 'error' => 'Este cupón ya no está disponible (estado actual: ' . $cupon['estado'] . ').'];
        }

        $cupones->marcarCancelado($cuponId, $usuarioId, $motivo);

        AuditoriaService::registrar('cupones_promocion', $cuponId, 'editar',
            ['estado' => 'disponible'],
            ['estado' => 'cancelado', 'motivo' => $motivo]);

        return ['ok' => true];
    }
}
