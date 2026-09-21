<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\LealtadMovimiento;
use App\Models\Producto;
use App\Models\Recompensa;
use Throwable;

/**
 * Motor del programa de lealtad. La regla de negocio ("cada N cafés =
 * 1 café gratis") NUNCA está escrita como número fijo aquí: N siempre
 * se lee de configuracion_sistema.umbral_lealtad, así que cambiarlo
 * desde el panel cambia el comportamiento sin tocar código.
 *
 * Reglas que se cumplen en TODO método de esta clase:
 * - El saldo (`clientes.cafes_acumulados`) nunca se edita fuera de una
 *   transacción que también escribe su movimiento en
 *   `lealtad_movimientos` — nunca hay cambio de saldo sin historial.
 * - Toda fila que se bloquea (cliente o recompensa) usa
 *   `SELECT ... FOR UPDATE` dentro de una transacción, para que dos
 *   peticiones simultáneas nunca pisen el mismo cambio ni permitan un
 *   doble canje/doble generación.
 * - El cálculo de cuántas recompensas corresponden es enteramente del
 *   servidor: ni el cliente ni el cajero envían "cuántas recompensas
 *   generar" ni "cuánto vale el descuento".
 */
class LealtadService
{
    private const MAX_INTENTOS_PIN = 5;
    private const BLOQUEO_PIN_MINUTOS = 15;

    /** Con 31^8 combinaciones una colisión ya es rarísima; 10 intentos seguidos indicarían un fallo real. */
    private const MAX_INTENTOS_CODIGO = 10;

    public static function obtenerUmbral(): int
    {
        $valor = (new Configuracion())->obtenerTodas()['umbral_lealtad'] ?? '10';
        $umbral = (int) $valor;

        return $umbral > 0 ? $umbral : 10;
    }

    /** Días que dura disponible una recompensa antes de vencer. null = no vence. */
    public static function obtenerDiasVencimiento(): ?int
    {
        $valor = (new Configuracion())->obtenerTodas()['lealtad_dias_vencimiento_recompensa'] ?? '';
        $dias = (int) $valor;

        return $dias > 0 ? $dias : null;
    }

    /**
     * Registra la compra de $cantidad cafés. Si el nuevo saldo cruza el
     * umbral una o más veces, genera automáticamente esa cantidad de
     * recompensas (ver docs/negocio.md — ej. sumar 25 desde 0 genera 2
     * recompensas y deja 5 acumulados, todo en una sola llamada).
     *
     * @return array{ok:bool,error?:string,codigos_generados?:array<int,string>}
     */
    public static function registrarConsumo(int $clienteId, int $cantidad, int $usuarioId, ?string $nota = null): array
    {
        if ($cantidad <= 0) {
            return ['ok' => false, 'error' => 'La cantidad debe ser mayor que cero.'];
        }

        $resultado = self::conCandado($clienteId, function (int $saldoActual) use ($clienteId, $cantidad, $usuarioId, $nota) {
            $movimientos = new LealtadMovimiento();
            $movimientos->insertar($clienteId, 'consumo', $cantidad, $usuarioId, $nota);

            return self::generarRecompensasSiCorresponde($clienteId, $saldoActual + $cantidad, $usuarioId);
        });

        if ($resultado['ok']) {
            AuditoriaService::registrar('lealtad_movimientos', $clienteId, 'consumo', null, [
                'cantidad' => $cantidad, 'codigos_generados' => $resultado['codigos_generados'],
            ]);
        }

        return $resultado;
    }

    /**
     * Ajuste manual (corrección de un error, cortesía, etc.). El
     * motivo es obligatorio: un ajuste sin explicación no es
     * trazabilidad real.
     *
     * @param string $tipo 'ajuste_positivo' o 'ajuste_negativo'
     * @return array{ok:bool,error?:string,codigos_generados?:array<int,string>}
     */
    public static function registrarAjuste(int $clienteId, int $cantidad, string $tipo, string $motivo, int $usuarioId): array
    {
        if (!in_array($tipo, ['ajuste_positivo', 'ajuste_negativo'], true)) {
            return ['ok' => false, 'error' => 'Tipo de ajuste inválido.'];
        }

        if ($cantidad <= 0) {
            return ['ok' => false, 'error' => 'La cantidad del ajuste debe ser mayor que cero.'];
        }

        if (trim($motivo) === '') {
            return ['ok' => false, 'error' => 'El motivo del ajuste es obligatorio.'];
        }

        $resultado = self::conCandado($clienteId, function (int $saldoActual) use ($clienteId, $cantidad, $tipo, $motivo, $usuarioId) {
            if ($tipo === 'ajuste_negativo' && $cantidad > $saldoActual) {
                return ['ok' => false, 'error' => "El cliente solo tiene {$saldoActual} café(s) acumulados; no se puede restar {$cantidad}."];
            }

            $cantidadConSigno = $tipo === 'ajuste_negativo' ? -$cantidad : $cantidad;
            (new LealtadMovimiento())->insertar($clienteId, $tipo, $cantidadConSigno, $usuarioId, $motivo);

            $nuevoSaldo = $saldoActual + $cantidadConSigno;

            if ($tipo === 'ajuste_positivo') {
                return self::generarRecompensasSiCorresponde($clienteId, $nuevoSaldo, $usuarioId);
            }

            Database::conexion()->prepare('UPDATE clientes SET cafes_acumulados = :saldo WHERE id = :id')
                ->execute(['saldo' => $nuevoSaldo, 'id' => $clienteId]);

            return ['ok' => true, 'codigos_generados' => []];
        });

        if ($resultado['ok']) {
            AuditoriaService::registrar('lealtad_movimientos', $clienteId, 'ajuste', null, [
                'tipo' => $tipo, 'cantidad' => $cantidad, 'motivo' => $motivo,
            ]);
        }

        return $resultado;
    }

    /**
     * Cancela una recompensa NO canjeada (ej. se generó por error) y le
     * devuelve al cliente los cafés que costó — nunca se pierden
     * silenciosamente.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function cancelarRecompensa(int $recompensaId, int $usuarioId, string $motivo): array
    {
        if (trim($motivo) === '') {
            return ['ok' => false, 'error' => 'El motivo de la cancelación es obligatorio.'];
        }

        $pdo = Database::conexion();
        $recompensas = new Recompensa();

        try {
            $pdo->beginTransaction();

            $recompensa = $recompensas->buscarPorIdConBloqueo($recompensaId);
            if ($recompensa === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Recompensa no encontrada.'];
            }

            if ($recompensa['estado'] !== 'disponible') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Esta recompensa ya no está disponible (estado actual: ' . $recompensa['estado'] . ').'];
            }

            $movimientoGenerador = (new LealtadMovimiento())->buscarPorId((int) $recompensa['movimiento_generador_id']);
            $cafesARestituir = $movimientoGenerador !== null ? abs((int) $movimientoGenerador['cantidad']) : self::obtenerUmbral();

            $stmtCliente = $pdo->prepare('SELECT cafes_acumulados FROM clientes WHERE id = :id FOR UPDATE');
            $stmtCliente->execute(['id' => $recompensa['cliente_id']]);
            $cliente = $stmtCliente->fetch();
            if ($cliente === false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Cliente no encontrado.'];
            }

            $movimientoId = (new LealtadMovimiento())->insertar(
                (int) $recompensa['cliente_id'],
                'recompensa_cancelada',
                $cafesARestituir,
                $usuarioId,
                $motivo,
                $recompensa['codigo']
            );

            $pdo->prepare('UPDATE clientes SET cafes_acumulados = cafes_acumulados + :cantidad WHERE id = :id')
                ->execute(['cantidad' => $cafesARestituir, 'id' => $recompensa['cliente_id']]);

            $recompensas->marcarCancelada($recompensaId, $usuarioId, $motivo, $movimientoId);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Logger::error('Error cancelando recompensa: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo cancelar la recompensa.'];
        }

        AuditoriaService::registrar('recompensas', $recompensaId, 'editar', ['estado' => 'disponible'], ['estado' => 'cancelada', 'motivo' => $motivo]);

        return ['ok' => true];
    }

    /**
     * Valida un código de recompensa y, si todo está en regla, la
     * marca canjeada (con fecha) y registra el producto entregado.
     * Verifica en este orden: existencia, PERTENENCIA (el código debe
     * ser de $clienteId, el cliente que el cajero identificó por
     * separado — un código ajeno nunca se canjea), disponibilidad y
     * vencimiento.
     *
     * @param int $clienteId cliente que dice ser el dueño; lo resuelve el
     *                       controlador desde el teléfono, nunca viene del código
     * @return array{ok:bool,error?:string,cliente_nombre?:string,producto_nombre?:string}
     */
    public static function validarYCanjearRecompensa(string $codigo, int $clienteId, int $productoId, int $usuarioId): array
    {
        $codigo = Recompensa::normalizarCodigo($codigo);
        if ($codigo === '') {
            return ['ok' => false, 'error' => 'Ingresa un código.'];
        }

        if ($clienteId <= 0) {
            return ['ok' => false, 'error' => 'Identifica primero al cliente.'];
        }

        $pdo = Database::conexion();
        $recompensas = new Recompensa();

        try {
            $pdo->beginTransaction();

            // FOR UPDATE: si dos cajeros intentan canjear el mismo
            // código al mismo tiempo, el segundo espera aquí y al
            // continuar ya ve estado='canjeada' — nunca doble canje.
            $recompensa = $recompensas->buscarPorCodigoConBloqueo($codigo);

            if ($recompensa === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Ese código no existe. Verifica que esté bien escrito.'];
            }

            // Pertenencia, ANTES de revelar nada del estado del código:
            // un código de otro cliente no dice si está canjeado, vencido, etc.
            if ((int) $recompensa['cliente_id'] !== $clienteId) {
                $pdo->rollBack();
                Logger::error(sprintf(
                    'Intento de canje con código ajeno: código %s pertenece al cliente %d, se intentó con el cliente %d (usuario %d).',
                    $codigo, (int) $recompensa['cliente_id'], $clienteId, $usuarioId
                ));
                return ['ok' => false, 'error' => 'Ese código no pertenece a este cliente.'];
            }

            if ($recompensa['estado'] === 'canjeada') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Esta recompensa ya fue canjeada el ' . $recompensa['fecha_canjeada'] . '.'];
            }

            if ($recompensa['estado'] === 'cancelada') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Esta recompensa fue cancelada y ya no es válida.'];
            }

            if ($recompensa['estado'] === 'vencida') {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Esta recompensa venció.'];
            }

            // estado === 'disponible': revisar vencimiento (perezoso —
            // se marca en este momento si ya pasó la fecha).
            if ($recompensa['fecha_vencimiento'] !== null && $recompensa['fecha_vencimiento'] < date('Y-m-d H:i:s')) {
                $recompensas->marcarVencida((int) $recompensa['id']);
                $pdo->commit();
                return ['ok' => false, 'error' => 'Esta recompensa venció el ' . $recompensa['fecha_vencimiento'] . '.'];
            }

            $producto = (new Producto())->buscarPorId($productoId);
            if ($producto === null || (int) $producto['activo'] !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Selecciona un producto activo del menú.'];
            }

            $movimientoId = (new LealtadMovimiento())->insertar(
                (int) $recompensa['cliente_id'],
                'recompensa_canjeada',
                0,
                $usuarioId,
                'Canje de recompensa: ' . $producto['nombre'],
                $codigo
            );

            // Defensa en profundidad: el UPDATE exige estado='disponible'.
            // Si por cualquier razón ya no lo estaba, no se confirma nada.
            if (!$recompensas->marcarCanjeada((int) $recompensa['id'], $productoId, $usuarioId, $movimientoId)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Esta recompensa ya no está disponible.'];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('Error canjeando recompensa: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo procesar el canje.'];
        }

        AuditoriaService::registrar('recompensas', (int) $recompensa['id'], 'canje',
            ['estado' => 'disponible'],
            ['estado' => 'canjeada', 'cliente_id' => $clienteId, 'producto_id' => $productoId, 'usuario_id' => $usuarioId]);

        $cliente = (new Cliente())->buscarPorId($clienteId);

        return [
            'ok'              => true,
            'cliente_nombre'  => $cliente['nombre'] ?? '',
            'producto_nombre' => $producto['nombre'],
        ];
    }

    /**
     * Ejecuta $accion dentro de una transacción con el cliente
     * bloqueado (FOR UPDATE), pasándole el saldo actual ya leído bajo
     * el candado. Centraliza el patrón "bloquear → transacción →
     * confirmar/deshacer → auditar" para consumo y ajustes.
     *
     * @param callable(int):array{ok:bool,error?:string,codigos_generados?:array<int,string>} $accion
     * @return array{ok:bool,error?:string,codigos_generados?:array<int,string>}
     */
    private static function conCandado(int $clienteId, callable $accion): array
    {
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

            $resultado = $accion((int) $cliente['cafes_acumulados']);

            if (!$resultado['ok']) {
                $pdo->rollBack();
                return $resultado;
            }

            $pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            $pdo->rollBack();
            Logger::error('Error en operación de lealtad: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo completar la operación.'];
        }
    }

    /**
     * Núcleo de la regla configurable: mientras el saldo alcance el
     * umbral, genera una recompensa y descuenta el umbral del saldo.
     * Debe llamarse siempre dentro de una transacción ya abierta (ver
     * conCandado()), con la fila del cliente ya bloqueada.
     *
     * @return array{ok:true,codigos_generados:array<int,string>}
     */
    private static function generarRecompensasSiCorresponde(int $clienteId, int $saldo, int $usuarioId): array
    {
        $umbral = self::obtenerUmbral();
        $diasVencimiento = self::obtenerDiasVencimiento();
        $codigosGenerados = [];

        while ($saldo >= $umbral) {
            $fechaVencimiento = $diasVencimiento !== null
                ? date('Y-m-d H:i:s', strtotime("+{$diasVencimiento} days"))
                : null;

            $codigosGenerados[] = self::crearRecompensaConCodigoUnico($clienteId, $umbral, $usuarioId, $fechaVencimiento);
            $saldo -= $umbral;
        }

        Database::conexion()->prepare('UPDATE clientes SET cafes_acumulados = :saldo WHERE id = :id')
            ->execute(['saldo' => $saldo, 'id' => $clienteId]);

        return ['ok' => true, 'codigos_generados' => $codigosGenerados];
    }

    /**
     * Crea UNA recompensa (su movimiento en el historial + su fila con el
     * código) garantizando que el código sea único.
     *
     * Comprobar "¿existe ya?" y luego guardar no es atómico: entre las
     * dos cosas otra petición puede haber generado el mismo código. La
     * garantía real es el UNIQUE de `recompensas.codigo`; si el INSERT
     * choca (error 1062) se deshace SOLO este intento (SAVEPOINT, para no
     * dejar un movimiento huérfano en el historial) y se prueba con otro
     * código, hasta MAX_INTENTOS_CODIGO veces. Cualquier otro error se
     * propaga tal cual.
     *
     * Debe llamarse dentro de una transacción ya abierta.
     *
     * @return string el código con el que quedó guardada la recompensa
     */
    private static function crearRecompensaConCodigoUnico(int $clienteId, int $umbral, int $usuarioId, ?string $fechaVencimiento): string
    {
        $pdo = Database::conexion();
        $movimientos = new LealtadMovimiento();
        $recompensas = new Recompensa();

        for ($intento = 1; $intento <= self::MAX_INTENTOS_CODIGO; $intento++) {
            $codigo = $recompensas->generarCodigoUnico();

            $pdo->exec('SAVEPOINT nueva_recompensa');

            try {
                $movimientoId = $movimientos->insertar($clienteId, 'recompensa_generada', -$umbral, $usuarioId, null, $codigo);

                $recompensas->crear([
                    'cliente_id'              => $clienteId,
                    'codigo'                  => $codigo,
                    'movimiento_generador_id' => $movimientoId,
                    'fecha_vencimiento'       => $fechaVencimiento,
                ]);

                $pdo->exec('RELEASE SAVEPOINT nueva_recompensa');

                return $codigo;
            } catch (\PDOException $e) {
                if (!Recompensa::esViolacionDeUnicidad($e)) {
                    throw $e;
                }

                $pdo->exec('ROLLBACK TO SAVEPOINT nueva_recompensa');
                Logger::error("Colisión de código de recompensa ({$codigo}) en el intento {$intento}; se genera otro.");
            }
        }

        throw new \RuntimeException('No se pudo generar un código de recompensa único tras ' . self::MAX_INTENTOS_CODIGO . ' intentos.');
    }
}
