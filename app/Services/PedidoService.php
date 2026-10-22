<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Dinero;
use App\Core\EnvioPedidos;
use App\Core\LechesProductos;
use App\Core\Logger;
use App\Core\PedidosDisponibles;
use App\Core\TemperaturaProductos;
use App\Core\Vista;
use App\Models\Configuracion;
use App\Models\Leche;
use PDO;
use PDOException;
use Throwable;

/**
 * Crea los pedidos del carrito público. El servidor NO confía en nada que venga del navegador salvo la clave de
 * cada línea (producto + versión + temperatura) y su cantidad: nombres, categorías, precios y totales se recalculan
 * con los datos reales de la base. Todo se guarda en UNA transacción (pedido + ítems + historial).
 *
 * Dinero en centavos enteros (App\Core\Dinero). Sintaxis compatible con PHP 7.4.
 */
class PedidoService
{
    public const MAX_LINEAS = 30;
    public const MAX_CANTIDAD = 20;
    public const MAX_TOTAL_CENTAVOS = 500000;      // Q5,000.00 por pedido
    public const LIMITE_POR_MINUTO = 10;            // intentos por IP en 60 s (guardados o rechazados)

    private const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // sin O/0/I/1/L

    /** Lo que dice el botón del carrito -> valor guardado. */
    private const FORMAS_PAGO = [
        'Pago contra entrega' => 'contra_entrega',
        'Transferencia'       => 'transferencia',
        'Tarjeta física'      => 'tarjeta_fisica',
        'contra_entrega'      => 'contra_entrega',
        'transferencia'       => 'transferencia',
        'tarjeta_fisica'      => 'tarjeta_fisica',
    ];

    /**
     * SOLO PARA PRUEBAS: si es invocable, se llama a mitad de la transacción (con los ítems ya insertados y antes
     * del historial) para simular un fallo de la base de datos y comprobar que no queda nada a medias.
     *
     * @var callable|null
     */
    public static $alFallarPrueba = null;

    /**
     * @param array<string,mixed> $entrada token, items[{clave,cantidad}], forma_pago, nombre, direccion, detalles,
     *        total_cliente (centavos o null), cliente_id (int|null), telefono (string|null)
     * @return array<string,mixed> ['ok'=>true, ...resumen] o ['ok'=>false,'error'=>codigo,'mensaje'=>texto, ...]
     */
    public static function crear(array $entrada, string $ip, ?string $userAgent = null): array
    {
        $token = isset($entrada['token']) && is_string($entrada['token']) ? $entrada['token'] : '';
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token)) {
            return self::error('token', 'No pudimos identificar tu pedido. Recarga la página e inténtalo de nuevo.');
        }

        // Normaliza las líneas (junta repetidas) para poder comparar y para la huella.
        $lineas = self::normalizarLineas(isset($entrada['items']) ? $entrada['items'] : null);
        if ($lineas === null) {
            return self::error('carrito', 'Tu carrito no tiene un formato válido. Recarga la página e inténtalo de nuevo.');
        }
        if ($lineas === []) {
            return self::error('carrito_vacio', 'Tu carrito está vacío.');
        }
        if (count($lineas) > self::MAX_LINEAS) {
            return self::error('demasiados', 'Tu pedido tiene demasiados productos distintos (máximo ' . self::MAX_LINEAS . ').');
        }

        $nombre = self::texto(isset($entrada['nombre']) ? $entrada['nombre'] : '', 60);
        $direccion = self::texto(isset($entrada['direccion']) ? $entrada['direccion'] : '', 200);
        $detalles = self::texto(isset($entrada['detalles']) ? $entrada['detalles'] : '', 300);
        $formaPago = isset($entrada['forma_pago']) && is_string($entrada['forma_pago']) && isset(self::FORMAS_PAGO[$entrada['forma_pago']])
            ? self::FORMAS_PAGO[$entrada['forma_pago']] : null;

        $huella = self::huella($lineas, $formaPago, $nombre, $direccion, $detalles);

        $pdo = Database::conexion();

        // Idempotencia: el mismo token devuelve el mismo pedido; con otro contenido es un error (el cliente cambió el carrito).
        $existente = self::buscarPorToken($pdo, $token);
        if ($existente !== null) {
            if (hash_equals((string) $existente['huella_carrito'], $huella)) {
                $resumen = self::resumen($pdo, (int) $existente['id']);
                $resumen['repetido'] = true;

                return $resumen;
            }

            return self::error('token_reutilizado', 'Tu carrito cambió. Vuelve a tocar «Enviar pedido por WhatsApp».');
        }

        // Días sin pedidos / pausa manual (Configuración → Carrito de pedidos). Se valida aquí, no solo en la página.
        $disponibilidad = PedidosDisponibles::estado((new Configuracion())->obtenerTodas());
        if (!$disponibilidad['abierto']) {
            return self::error('pedidos_cerrados', $disponibilidad['mensaje']);
        }

        if ($formaPago === null) {
            return self::error('forma_pago', 'Elige la forma de pago.');
        }

        // Límite por IP (guardados + rechazados) para frenar abusos sin molestar a un cliente normal.
        if (self::demasiadosIntentos($pdo, $ip)) {
            return self::error('limite', 'Estás enviando pedidos muy rápido. Espera un minuto e inténtalo de nuevo.');
        }
        self::registrarIntento($pdo, false, $ip, $userAgent);   // se marca como éxito más abajo si se guarda

        // --- Recalcular con los datos reales ---
        $resuelto = self::resolver($pdo, $lineas);
        if (isset($resuelto['error'])) {
            return $resuelto;
        }
        $items = $resuelto['items'];

        // Envío a domicilio (Configuración → Carrito de pedidos): se cobra, o es gratis desde N bebidas, y se guarda
        // como una línea más del pedido para que el total siempre sea la suma de sus líneas.
        $bebidas = 0;
        foreach ($items as $it) {
            if ($it['es_bebida']) {
                $bebidas += $it['cantidad'];
            }
        }
        $envio = EnvioPedidos::calcular((new Configuracion())->obtenerTodas(), $bebidas);
        if ($envio['cobra']) {
            $items[] = self::lineaEnvio($envio['costo_centavos'], false);
        } elseif ($envio['gratis']) {
            $items[] = self::lineaEnvio(0, true);
        }

        $subtotal = 0;
        foreach ($items as $it) {
            $subtotal += $it['subtotal_centavos'];
        }
        $descuento = 0;   // el carrito no aplica descuentos, cupones ni recompensas
        $total = $subtotal - $descuento;

        if ($total <= 0 || $total > self::MAX_TOTAL_CENTAVOS) {
            return self::error('total', 'El total del pedido está fuera del rango permitido (máximo ' . Dinero::formato(self::MAX_TOTAL_CENTAVOS) . ').');
        }

        $totalCliente = isset($entrada['total_cliente']) && is_int($entrada['total_cliente']) ? $entrada['total_cliente'] : null;
        if ($totalCliente !== null && $totalCliente !== $total) {
            return [
                'ok'              => false,
                'error'           => 'precios_cambiaron',
                'mensaje'         => 'Algunos precios cambiaron mientras armabas tu pedido. El total correcto es ' . Dinero::formato($total)
                    . '. Revisa tu pedido y vuelve a enviarlo.',
                'total_centavos'  => $total,
                'total'           => Dinero::formato($total),
                'precios'         => self::preciosPorClave($items),
                'envio_reglas'    => EnvioPedidos::paraCarrito((new Configuracion())->obtenerTodas()),
            ];
        }

        $clienteId = isset($entrada['cliente_id']) && is_int($entrada['cliente_id']) ? $entrada['cliente_id'] : null;
        $telefono = isset($entrada['telefono']) && is_string($entrada['telefono']) ? self::texto($entrada['telefono'], 20) : null;

        // --- Guardar: todo o nada ---
        for ($intento = 1; $intento <= 6; $intento++) {
            $codigo = self::nuevoCodigo();

            try {
                $pdo->beginTransaction();

                $pdo->prepare(
                    'INSERT INTO pedidos
                        (codigo, token_idempotencia, huella_carrito, cliente_nombre, cliente_telefono, cliente_id, direccion,
                         forma_pago, subtotal_centavos, descuento_centavos, total_centavos, estado, ip, nota)
                     VALUES
                        (:codigo, :token, :huella, :nombre, :telefono, :cliente_id, :direccion,
                         :forma_pago, :subtotal, :descuento, :total, :estado, :ip, :nota)'
                )->execute([
                    'codigo'     => $codigo,
                    'token'      => $token,
                    'huella'     => $huella,
                    'nombre'     => $nombre !== '' ? $nombre : null,
                    'telefono'   => $telefono !== null && $telefono !== '' ? $telefono : null,
                    'cliente_id' => $clienteId,
                    'direccion'  => $direccion !== '' ? $direccion : null,
                    'forma_pago' => $formaPago,
                    'subtotal'   => $subtotal,
                    'descuento'  => $descuento,
                    'total'      => $total,
                    'estado'     => 'pendiente',
                    'ip'         => $ip,
                    'nota'       => $detalles !== '' ? $detalles : null,
                ]);
                $pedidoId = (int) $pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO pedido_items
                        (pedido_id, producto_id, clave, nombre_snapshot, categoria_snapshot, tipo_snapshot,
                         leche_snapshot, leche_extra_centavos,
                         precio_unitario_centavos, cantidad, subtotal_centavos, es_cortesia)
                     VALUES
                        (:pedido_id, :producto_id, :clave, :nombre, :categoria, :tipo,
                         :leche, :leche_extra, :precio, :cantidad, :subtotal, :cortesia)'
                );
                foreach ($items as $it) {
                    $stmtItem->execute([
                        'pedido_id'   => $pedidoId,
                        'producto_id' => $it['producto_id'],
                        'clave'       => $it['clave'],
                        'nombre'      => $it['nombre'],
                        'categoria'   => $it['categoria'],
                        'tipo'        => $it['tipo'],
                        'leche'       => $it['leche'],
                        'leche_extra' => $it['leche_extra_centavos'],
                        'precio'      => $it['precio_centavos'],
                        'cantidad'    => $it['cantidad'],
                        'subtotal'    => $it['subtotal_centavos'],
                        'cortesia'    => $it['es_cortesia'] ? 1 : 0,
                    ]);
                }

                if (is_callable(self::$alFallarPrueba)) {
                    call_user_func(self::$alFallarPrueba, $pedidoId);
                }

                $pdo->prepare(
                    'INSERT INTO pedido_historial (pedido_id, estado_anterior, estado_nuevo, usuario_id, motivo)
                     VALUES (:pedido_id, NULL, :estado, NULL, :motivo)'
                )->execute([
                    'pedido_id' => $pedidoId,
                    'estado'    => 'pendiente',
                    'motivo'    => 'Pedido guardado al tocar «Enviar por WhatsApp»; falta que el cliente confirme que lo envió',
                ]);

                $pdo->commit();

                self::registrarIntento($pdo, true, $ip, $userAgent);

                $resumen = self::resumen($pdo, $pedidoId);
                $resumen['repetido'] = false;

                return $resumen;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $duplicado = (int) (isset($e->errorInfo[1]) ? $e->errorInfo[1] : 0) === 1062;
                if ($duplicado && strpos($e->getMessage(), 'uq_pedidos_token') !== false) {
                    // Dos peticiones con el mismo token a la vez: gana la primera, esta devuelve ese pedido.
                    $otro = self::buscarPorToken($pdo, $token);
                    if ($otro !== null && hash_equals((string) $otro['huella_carrito'], $huella)) {
                        $resumen = self::resumen($pdo, (int) $otro['id']);
                        $resumen['repetido'] = true;

                        return $resumen;
                    }
                }
                if ($duplicado && strpos($e->getMessage(), 'uq_pedidos_codigo') !== false) {
                    continue;   // el código ya existía: se intenta con otro
                }

                Logger::error('No se pudo guardar el pedido: ' . $e->getMessage());

                return self::error('servidor', 'No pudimos guardar tu pedido. Tu carrito sigue aquí; inténtalo de nuevo en un momento.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Logger::error('Fallo al guardar el pedido: ' . $e->getMessage());

                return self::error('servidor', 'No pudimos guardar tu pedido. Tu carrito sigue aquí; inténtalo de nuevo en un momento.');
            }
        }

        return self::error('servidor', 'No pudimos guardar tu pedido. Tu carrito sigue aquí; inténtalo de nuevo en un momento.');
    }

    /**
     * El cliente confirma que SÍ envió el mensaje de WhatsApp («Sí, ya lo envié»): el pedido pasa de `pendiente` a
     * `enviado`. Idempotente: confirmar dos veces (o un pedido que el personal ya confirmó) no cambia nada.
     *
     * @return array{ok:bool,estado?:string}
     */
    public static function confirmarEnvio(string $token): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token)) {
            return ['ok' => false];
        }

        $pdo = Database::conexion();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'enviado' WHERE token_idempotencia = :token AND estado = 'pendiente'");
            $stmt->execute(['token' => $token]);

            if ($stmt->rowCount() === 1) {
                $pdo->prepare(
                    "INSERT INTO pedido_historial (pedido_id, estado_anterior, estado_nuevo, usuario_id, motivo)
                     SELECT id, 'pendiente', 'enviado', NULL, 'El cliente confirmó que envió el mensaje de WhatsApp'
                     FROM pedidos WHERE token_idempotencia = :token"
                )->execute(['token' => $token]);
            }

            $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE token_idempotencia = :token');
            $stmt->execute(['token' => $token]);
            $estado = $stmt->fetchColumn();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error('No se pudo confirmar el envío del pedido: ' . $e->getMessage());

            return ['ok' => false];
        }

        return $estado === false ? ['ok' => false] : ['ok' => true, 'estado' => (string) $estado];
    }

    /**
     * @param mixed $items lista de ['clave' => string, 'cantidad' => int]
     * @return array<string,int>|null clave => cantidad (juntando repetidas), o null si el formato es inválido
     */
    private static function normalizarLineas($items): ?array
    {
        if (!is_array($items)) {
            return null;
        }

        $lineas = [];
        foreach ($items as $it) {
            if (!is_array($it) || !isset($it['clave'], $it['cantidad']) || !is_string($it['clave'])) {
                return null;
            }
            $clave = trim($it['clave']);
            if (!preg_match('/^\d{1,10}(-alt)?(~[cf])?(~l\d{1,6})?$/', $clave)) {
                return null;
            }
            $cantidad = $it['cantidad'];
            if (is_string($cantidad) && preg_match('/^\d{1,3}$/', $cantidad)) {
                $cantidad = (int) $cantidad;
            }
            if (!is_int($cantidad) || $cantidad < 1 || $cantidad > self::MAX_CANTIDAD) {
                return null;
            }
            $lineas[$clave] = (isset($lineas[$clave]) ? $lineas[$clave] : 0) + $cantidad;
            if ($lineas[$clave] > self::MAX_CANTIDAD) {
                return null;
            }
        }

        ksort($lineas);

        return $lineas;
    }

    /**
     * Resuelve cada clave contra la base: producto activo en categoría activa, versión y temperatura válidas.
     *
     * @param array<string,int> $lineas
     * @return array<string,mixed> ['items'=>[...]] o un arreglo de error
     */
    private static function resolver(PDO $pdo, array $lineas): array
    {
        $ids = [];
        foreach (array_keys($lineas) as $clave) {
            // Las claves numéricas ("12") llegan como int: PHP convierte así las claves de un arreglo.
            preg_match('/^(\d+)/', (string) $clave, $m);
            $ids[(int) $m[1]] = true;
        }
        $ids = array_keys($ids);

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT p.id, p.nombre, p.precio, p.precio_alt, p.etiqueta_precio_alt, p.activo,
                    c.id AS cat_id, c.nombre AS cat_nombre, c.activo AS cat_activo
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id IN ({$marcas})"
        );
        $stmt->execute($ids);
        $productos = [];
        foreach ($stmt->fetchAll() as $fila) {
            $productos[(int) $fila['id']] = $fila;
        }

        $configuracion = (new Configuracion())->obtenerTodas();
        $temperaturas = TemperaturaProductos::mapa($configuracion);
        $productosConLeche = LechesProductos::mapa($configuracion);
        $categoriasBebida = EnvioPedidos::categorias($configuracion);
        $leches = [];
        foreach ((new Leche())->listarActivas() as $l) {
            $leches[(int) $l['id']] = $l;
        }

        $items = [];
        $noDisponibles = [];
        foreach ($lineas as $clave => $cantidad) {
            $clave = (string) $clave;
            preg_match('/^(\d+)(-alt)?(~([cf]))?(~l(\d+))?$/', $clave, $m);
            $id = (int) $m[1];
            $esAlt = !empty($m[2]);
            $temp = isset($m[4]) ? $m[4] : '';
            $lecheId = isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0;

            $p = isset($productos[$id]) ? $productos[$id] : null;
            if ($p === null || (int) $p['activo'] !== 1 || (int) $p['cat_activo'] !== 1) {
                $noDisponibles[] = $clave;
                continue;
            }

            // La temperatura pedida debe ser la que el producto permite (ambas -> c o f; caliente -> c; frio -> f; ninguna -> sin sufijo).
            $permitida = isset($temperaturas[$id]) ? $temperaturas[$id] : '';
            $okTemp = ($permitida === 'ambas' && ($temp === 'c' || $temp === 'f'))
                || ($permitida === 'caliente' && $temp === 'c')
                || ($permitida === 'frio' && $temp === 'f')
                || ($permitida === '' && $temp === '');
            if (!$okTemp) {
                $noDisponibles[] = $clave;
                continue;
            }

            if ($esAlt && ($p['precio_alt'] === null || $p['precio_alt'] === '' || (float) $p['precio_alt'] <= 0)) {
                $noDisponibles[] = $clave;
                continue;
            }

            $variantes = Vista::variantesPedido($p, (string) $p['cat_nombre']);
            $variante = null;
            foreach ($variantes as $v) {
                if ($v['clave'] === ($esAlt ? $id . '-alt' : (string) $id)) {
                    $variante = $v;
                }
            }
            if ($variante === null) {
                $noDisponibles[] = $clave;
                continue;
            }

            $precio = Dinero::aCentavos((string) ($esAlt ? $p['precio_alt'] : $p['precio']));
            if ($precio <= 0) {
                $noDisponibles[] = $clave;
                continue;
            }

            // Leche: un producto que la permite exige elegir una leche activa; uno que no, no la admite.
            $leche = null;
            $extraLeche = 0;
            if (isset($productosConLeche[$id]) && $leches !== []) {   // sin ninguna leche activa, el producto se pide sin leche
                if ($lecheId === 0 || !isset($leches[$lecheId])) {
                    $noDisponibles[] = $clave;
                    continue;
                }
                $leche = (string) $leches[$lecheId]['nombre'];
                $extraLeche = Dinero::aCentavos((string) $leches[$lecheId]['precio_extra']);
                $precio += $extraLeche;
            } elseif ($lecheId !== 0) {
                $noDisponibles[] = $clave;
                continue;
            }

            $nombreTemp = $temp === 'c' ? 'Caliente' : ($temp === 'f' ? 'Frío' : '');
            $items[] = [
                'clave'             => $clave,
                'producto_id'       => $id,
                'nombre'            => $variante['nombre'] . ($nombreTemp !== '' ? ' - ' . $nombreTemp : ''),
                'categoria'         => (string) $p['cat_nombre'],
                'tipo'              => $variante['corta'] . ($nombreTemp !== '' ? ' · ' . $nombreTemp : ''),
                'leche'             => $leche,
                'leche_extra_centavos' => $extraLeche,
                'es_bebida'         => isset($categoriasBebida[(int) $p['cat_id']]),
                'es_cortesia'       => false,
                'precio_centavos'   => $precio,
                'cantidad'          => (int) $cantidad,
                'subtotal_centavos' => $precio * (int) $cantidad,
            ];
        }

        if ($noDisponibles !== []) {
            return [
                'ok'      => false,
                'error'   => 'no_disponible',
                'mensaje' => 'Algunos productos de tu carrito ya no están disponibles y se quitaron. Revisa tu pedido y vuelve a enviarlo.',
                'claves'  => $noDisponibles,
            ];
        }

        return ['items' => $items];
    }

    /**
     * La línea del pedido que representa el envío: con costo, o gratis (cortesía de precio 0, que no cuenta como ingreso).
     *
     * @return array<string,mixed>
     */
    private static function lineaEnvio(int $costoCentavos, bool $gratis): array
    {
        return [
            'clave'                => EnvioPedidos::CLAVE_LINEA,
            'producto_id'          => null,
            'nombre'               => EnvioPedidos::NOMBRE_LINEA . ($gratis ? ' (gratis)' : ''),
            'categoria'            => EnvioPedidos::CATEGORIA_LINEA,
            'tipo'                 => EnvioPedidos::CATEGORIA_LINEA,
            'leche'                => null,
            'leche_extra_centavos' => 0,
            'es_bebida'            => false,
            'es_cortesia'          => $gratis,
            'precio_centavos'      => $gratis ? 0 : $costoCentavos,
            'cantidad'             => 1,
            'subtotal_centavos'    => $gratis ? 0 : $costoCentavos,
        ];
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,int> */
    private static function preciosPorClave(array $items): array
    {
        $r = [];
        foreach ($items as $it) {
            $r[$it['clave']] = $it['precio_centavos'];
        }

        return $r;
    }

    /** @return array<string,mixed> */
    private static function resumen(PDO $pdo, int $pedidoId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id');
        $stmt->execute(['id' => $pedidoId]);
        $p = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT * FROM pedido_items WHERE pedido_id = :id ORDER BY id');
        $stmt->execute(['id' => $pedidoId]);
        $items = [];
        $envio = ['centavos' => 0, 'gratis' => false, 'texto' => ''];
        foreach ($stmt->fetchAll() as $it) {
            if ($it['clave'] === EnvioPedidos::CLAVE_LINEA) {
                // El envío no es un producto: se informa aparte («Envío: Q5.00» o «Envío: GRATIS»).
                $envio = [
                    'centavos' => (int) $it['subtotal_centavos'],
                    'gratis'   => (int) $it['es_cortesia'] === 1,
                    'texto'    => (int) $it['es_cortesia'] === 1 ? 'GRATIS' : Dinero::formato((int) $it['subtotal_centavos']),
                ];
                continue;
            }
            $items[] = [
                'clave'           => $it['clave'],
                'nombre'          => $it['nombre_snapshot'],
                'leche'           => $it['leche_snapshot'],
                'cantidad'        => (int) $it['cantidad'],
                'precio_centavos' => (int) $it['precio_unitario_centavos'],
                'precio'          => Dinero::formato((int) $it['precio_unitario_centavos']),
                'subtotal'        => Dinero::formato((int) $it['subtotal_centavos']),
            ];
        }

        return [
            'ok'              => true,
            'id'              => (int) $p['id'],
            'codigo'          => $p['codigo'],
            'total_centavos'  => (int) $p['total_centavos'],
            'total'           => Dinero::formato((int) $p['total_centavos']),
            'items'           => $items,
            'envio'           => $envio,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function buscarPorToken(PDO $pdo, string $token): ?array
    {
        $stmt = $pdo->prepare('SELECT id, huella_carrito FROM pedidos WHERE token_idempotencia = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    private static function demasiadosIntentos(PDO $pdo, string $ip): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM intentos_login
             WHERE usuario_o_ip = 'pedido' AND ip = :ip AND creado_en >= DATE_SUB(NOW(), INTERVAL 60 SECOND)"
        );
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn() >= self::LIMITE_POR_MINUTO;
    }

    private static function registrarIntento(PDO $pdo, bool $guardado, string $ip, ?string $userAgent): void
    {
        try {
            if ($guardado) {
                // El intento registrado antes de guardar ya cuenta; solo se marca como exitoso.
                $pdo->prepare(
                    "UPDATE intentos_login SET exitoso = 1
                     WHERE usuario_o_ip = 'pedido' AND ip = :ip AND exitoso = 0
                     ORDER BY id DESC LIMIT 1"
                )->execute(['ip' => $ip]);

                return;
            }
            $pdo->prepare(
                "INSERT INTO intentos_login (usuario_o_ip, exitoso, ip, user_agent) VALUES ('pedido', 0, :ip, :ua)"
            )->execute(['ip' => $ip, 'ua' => $userAgent !== null ? substr($userAgent, 0, 255) : null]);
        } catch (Throwable $e) {
            Logger::error('No se pudo registrar el intento de pedido: ' . $e->getMessage());
        }
    }

    private static function nuevoCodigo(): string
    {
        $max = strlen(self::ALFABETO_CODIGO) - 1;
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= self::ALFABETO_CODIGO[random_int(0, $max)];
        }

        return $codigo;
    }

    /** @param mixed $valor */
    private static function texto($valor, int $maximo): string
    {
        if (!is_string($valor)) {
            return '';
        }
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return mb_substr($valor, 0, $maximo);
    }

    /** @param array<string,int> $lineas */
    private static function huella(array $lineas, ?string $formaPago, string $nombre, string $direccion, string $detalles): string
    {
        return hash('sha256', json_encode([$lineas, $formaPago, $nombre, $direccion, $detalles]));
    }

    /** @return array<string,mixed> */
    private static function error(string $codigo, string $mensaje): array
    {
        return ['ok' => false, 'error' => $codigo, 'mensaje' => $mensaje];
    }
}
