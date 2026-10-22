<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envío a domicilio de los pedidos del carrito. Configuración → Carrito de pedidos → Envío a domicilio:
 *
 *  - envio_activo        1 = se cobra envío, 0 = nunca se cobra
 *  - envio_costo         lo que cuesta (en quetzales; p. ej. 5.00)
 *  - envio_gratis_desde  envío gratis a partir de esta cantidad de BEBIDAS (0 = nunca es gratis)
 *  - envio_categorias    categorías cuyas unidades cuentan como bebidas (lista de id en JSON); las demás (sándwiches,
 *                        panes, roles de canela…) no cuentan
 *
 * La misma regla la aplican el servidor (PedidoService, que guarda el envío como una línea «Envío a domicilio» del
 * pedido) y el carrito (carrito.js). Dinero en centavos enteros. Compatible con PHP 7.4.
 */
class EnvioPedidos
{
    public const CLAVE_ACTIVO = 'envio_activo';
    public const CLAVE_COSTO = 'envio_costo';
    public const CLAVE_GRATIS_DESDE = 'envio_gratis_desde';
    public const CLAVE_CATEGORIAS = 'envio_categorias';

    public const COSTO_DEFECTO = '5.00';
    public const GRATIS_DESDE_DEFECTO = 5;
    public const COSTO_MAXIMO = 100.0;
    public const GRATIS_DESDE_MAXIMO = 50;

    /** Nombre y categoría de la línea del pedido que representa el envío. */
    public const CLAVE_LINEA = 'envio';
    public const NOMBRE_LINEA = 'Envío a domicilio';
    public const CATEGORIA_LINEA = 'Envío';

    /**
     * @param array<string,string> $config
     * @return array{activo:bool,costo_centavos:int,gratis_desde:int,categorias:array<int,int>}
     */
    public static function reglas(array $config): array
    {
        $costo = isset($config[self::CLAVE_COSTO]) && preg_match('/^\d{1,6}(\.\d{1,2})?$/', trim((string) $config[self::CLAVE_COSTO]))
            ? trim((string) $config[self::CLAVE_COSTO]) : self::COSTO_DEFECTO;
        $gratis = isset($config[self::CLAVE_GRATIS_DESDE]) && ctype_digit(trim((string) $config[self::CLAVE_GRATIS_DESDE]))
            ? (int) trim((string) $config[self::CLAVE_GRATIS_DESDE]) : self::GRATIS_DESDE_DEFECTO;

        return [
            'activo'         => (isset($config[self::CLAVE_ACTIVO]) ? $config[self::CLAVE_ACTIVO] : '1') !== '0',
            'costo_centavos' => Dinero::aCentavos($costo),
            'gratis_desde'   => $gratis,
            'categorias'     => self::categorias($config),
        ];
    }

    /**
     * @param array<string,string> $config
     * @return array<int,int> id de categoría => id
     */
    public static function categorias(array $config): array
    {
        $json = json_decode((string) (isset($config[self::CLAVE_CATEGORIAS]) ? $config[self::CLAVE_CATEGORIAS] : ''), true);
        if (!is_array($json)) {
            return [];
        }
        $mapa = [];
        foreach ($json as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $mapa[(int) $id] = (int) $id;
            }
        }

        return $mapa;
    }

    /**
     * Lo que se cobra por el envío de un pedido con esa cantidad de bebidas.
     *
     * @param array<string,string> $config
     * @return array{cobra:bool,costo_centavos:int,gratis:bool,faltan:int}
     *         cobra: hay costo de envío; gratis: la regla de bebidas lo dejó sin costo; faltan: bebidas que faltan para el envío gratis (0 si ya aplica o no hay regla)
     */
    public static function calcular(array $config, int $bebidas): array
    {
        $r = self::reglas($config);
        if (!$r['activo'] || $r['costo_centavos'] <= 0) {
            return ['cobra' => false, 'costo_centavos' => 0, 'gratis' => false, 'faltan' => 0];
        }
        if ($r['gratis_desde'] > 0 && $bebidas >= $r['gratis_desde']) {
            return ['cobra' => false, 'costo_centavos' => 0, 'gratis' => true, 'faltan' => 0];
        }

        return [
            'cobra'          => true,
            'costo_centavos' => $r['costo_centavos'],
            'gratis'         => false,
            'faltan'         => $r['gratis_desde'] > 0 ? $r['gratis_desde'] - $bebidas : 0,
        ];
    }

    /**
     * Datos para el carrito (JavaScript).
     *
     * @param array<string,string> $config
     * @return array{activo:bool,costo:int,gratisDesde:int}
     */
    public static function paraCarrito(array $config): array
    {
        $r = self::reglas($config);

        return ['activo' => $r['activo'] && $r['costo_centavos'] > 0, 'costo' => $r['costo_centavos'], 'gratisDesde' => $r['gratis_desde']];
    }
}
