<?php

declare(strict_types=1);

namespace App\Core;

/**
 * ¿Se pueden hacer pedidos por la página hoy? Dos controles, ambos en Configuración → Carrito de pedidos:
 *
 *  - Días de la semana en que se reciben pedidos (p. ej. sin repartidor sábado y domingo). Cualquier día se puede quitar.
 *  - Pausa manual: apaga los pedidos de inmediato (imprevistos, fiestas, falta de personal) con un mensaje opcional.
 *
 * El menú siempre se puede ver; lo que se detiene es agregar al carrito y enviar el pedido. La misma regla la aplican
 * el servidor (PedidoService::crear: aunque alguien manipule la página no entra un pedido) y el carrito (carrito.js),
 * que muestra el aviso y el próximo día en que se vuelven a recibir pedidos. El día se toma en la zona horaria del negocio.
 * Compatible con PHP 7.4.
 */
class PedidosDisponibles
{
    public const CLAVE_DIAS = 'pedidos_dias';
    public const CLAVE_PAUSA = 'pedidos_pausados';
    public const CLAVE_MENSAJE = 'pedidos_mensaje';

    public const DIAS_TODOS = '0,1,2,3,4,5,6';
    public const MENSAJE_PAUSA_DEFECTO = 'Por el momento no estamos recibiendo pedidos por la página.';

    /** 0 = domingo … 6 = sábado */
    public const NOMBRES_DIA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /**
     * Días (0-6) en que se reciben pedidos. Sin configurar (instalación anterior a esta función): todos.
     *
     * @param array<string,string> $config
     * @return array<int,int>
     */
    public static function diasActivos(array $config): array
    {
        $crudo = array_key_exists(self::CLAVE_DIAS, $config) ? trim((string) $config[self::CLAVE_DIAS]) : self::DIAS_TODOS;
        if ($crudo === '') {
            return [];   // ningún día: los pedidos por la página están apagados
        }

        $dias = [];
        foreach (explode(',', $crudo) as $d) {
            $d = trim($d);
            if ($d !== '' && ctype_digit($d) && (int) $d >= 0 && (int) $d <= 6) {
                $dias[(int) $d] = (int) $d;
            }
        }
        ksort($dias);

        return array_values($dias);
    }

    /** @param array<string,string> $config */
    public static function pausado(array $config): bool
    {
        return ($config[self::CLAVE_PAUSA] ?? '0') === '1';
    }

    /** @param array<string,string> $config */
    public static function mensajePausa(array $config): string
    {
        $texto = trim((string) ($config[self::CLAVE_MENSAJE] ?? ''));

        return $texto !== '' ? $texto : self::MENSAJE_PAUSA_DEFECTO;
    }

    /**
     * @param array<string,string> $config
     * @param int|null $diaSemana 0-6; por omisión, hoy en la zona horaria del negocio (la de PHP)
     * @return array{abierto:bool,motivo:string,mensaje:string} motivo: '' | 'pausa' | 'dia'
     */
    public static function estado(array $config, ?int $diaSemana = null): array
    {
        if (self::pausado($config)) {
            return ['abierto' => false, 'motivo' => 'pausa', 'mensaje' => self::mensajePausa($config)];
        }

        $hoy = $diaSemana !== null ? $diaSemana : (int) date('w');
        $dias = self::diasActivos($config);
        if (in_array($hoy, $dias, true)) {
            return ['abierto' => true, 'motivo' => '', 'mensaje' => ''];
        }

        $proximo = self::proximoDia($dias, $hoy);
        $mensaje = $dias === []
            ? 'Por ahora no recibimos pedidos por la página.'
            : 'Hoy no recibimos pedidos a domicilio.' . ($proximo !== '' ? ' Volvemos a recibirlos ' . $proximo . '.' : '');

        return ['abierto' => false, 'motivo' => 'dia', 'mensaje' => $mensaje];
    }

    /**
     * Datos para el carrito (JavaScript): vuelve a evaluar el día cada vez, así la página abierta pasada la medianoche
     * se actualiza sola.
     *
     * @param array<string,string> $config
     * @return array{dias:array<int,int>,pausa:bool,mensajePausa:string}
     */
    public static function paraCarrito(array $config): array
    {
        return [
            'dias'         => self::diasActivos($config),
            'pausa'        => self::pausado($config),
            'mensajePausa' => self::mensajePausa($config),
        ];
    }

    /**
     * «mañana (lunes)» o «el miércoles»: la próxima vez que se reciben pedidos; '' si ningún día está activo.
     *
     * @param array<int,int> $dias
     */
    private static function proximoDia(array $dias, int $hoy): string
    {
        for ($desplazamiento = 1; $desplazamiento <= 7; $desplazamiento++) {
            $dia = ($hoy + $desplazamiento) % 7;
            if (in_array($dia, $dias, true)) {
                return $desplazamiento === 1
                    ? 'mañana (' . self::NOMBRES_DIA[$dia] . ')'
                    : 'el ' . self::NOMBRES_DIA[$dia];
            }
        }

        return '';
    }
}
