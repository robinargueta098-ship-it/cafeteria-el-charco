<?php

declare(strict_types=1);

namespace App\Core;

use DateTime;

/**
 * Filtro de periodo común a todos los reportes. Se lee de la URL (GET) para que sea compartible.
 *
 *   periodo = hoy | ayer | 7dias | mes | mes_anterior | rango   (con rango: desde=Y-m-d&hasta=Y-m-d)
 *
 * Las fechas son de calendario en hora de Guatemala; el rango es inclusivo de 00:00:00 a 23:59:59.
 * Reglas del rango personalizado: fechas reales, desde <= hasta, máximo 366 días, hasta no futura.
 * Compatible con PHP 7.4.
 */
class ReportePeriodo
{
    public const OPCIONES = [
        'hoy'          => 'Hoy',
        'ayer'         => 'Ayer',
        '7dias'        => 'Últimos 7 días',
        'mes'          => 'Mes actual',
        'mes_anterior' => 'Mes anterior',
        'rango'        => 'Rango personalizado',
    ];

    public const MAX_DIAS = 366;

    /**
     * @param array<string,mixed> $get
     * @return array{clave:string,desde:string,hasta:string,etiqueta:string,errores:array<int,string>,dias:int}
     */
    public static function resolver(array $get, ?string $hoy = null): array
    {
        $hoy = $hoy !== null ? $hoy : date('Y-m-d');
        $clave = isset($get['periodo']) && is_string($get['periodo']) && isset(self::OPCIONES[$get['periodo']]) ? $get['periodo'] : 'mes';
        $errores = [];

        if ($clave === 'rango') {
            $desde = isset($get['desde']) && is_string($get['desde']) ? trim($get['desde']) : '';
            $hasta = isset($get['hasta']) && is_string($get['hasta']) ? trim($get['hasta']) : '';

            $problema = null;
            if (!Validator::esFechaValida($desde) || !Validator::esFechaValida($hasta)) {
                $problema = 'Escribe una fecha de inicio y una de fin válidas.';
            } elseif ($desde > $hasta) {
                $problema = 'La fecha de inicio no puede ser posterior a la de fin.';
            } elseif ($hasta > $hoy) {
                $problema = 'La fecha de fin no puede ser futura.';
            } elseif (self::diasEntre($desde, $hasta) > self::MAX_DIAS) {
                $problema = 'El rango no puede pasar de ' . self::MAX_DIAS . ' días.';
            }

            if ($problema === null) {
                return self::armar('rango', $desde, $hasta, []);
            }

            // Rango inválido: se muestra el mes actual y se explica por qué.
            $errores[] = $problema . ' Se muestra el mes actual.';
            $clave = 'mes';
        }

        switch ($clave) {
            case 'hoy':
                $desde = $hasta = $hoy;
                break;
            case 'ayer':
                $desde = $hasta = self::sumarDias($hoy, -1);
                break;
            case '7dias':
                $desde = self::sumarDias($hoy, -6);
                $hasta = $hoy;
                break;
            case 'mes_anterior':
                $primeroEsteMes = substr($hoy, 0, 8) . '01';
                $hasta = self::sumarDias($primeroEsteMes, -1);
                $desde = substr($hasta, 0, 8) . '01';
                break;
            case 'mes':
            default:
                $clave = 'mes';
                $desde = substr($hoy, 0, 8) . '01';
                $hasta = $hoy;
        }

        return self::armar($clave, $desde, $hasta, $errores);
    }

    /** @param array<int,string> $errores @return array{clave:string,desde:string,hasta:string,etiqueta:string,errores:array<int,string>,dias:int} */
    private static function armar(string $clave, string $desde, string $hasta, array $errores): array
    {
        $fmt = function (string $f): string {
            return substr($f, 8, 2) . '/' . substr($f, 5, 2) . '/' . substr($f, 0, 4);
        };

        return [
            'clave'    => $clave,
            'desde'    => $desde,
            'hasta'    => $hasta,
            'etiqueta' => $desde === $hasta ? 'El ' . $fmt($desde) : 'Del ' . $fmt($desde) . ' al ' . $fmt($hasta),
            'errores'  => $errores,
            'dias'     => self::diasEntre($desde, $hasta),
        ];
    }

    /** Días del rango, contando ambos extremos. */
    private static function diasEntre(string $desde, string $hasta): int
    {
        $a = new DateTime($desde);
        $b = new DateTime($hasta);

        return (int) $a->diff($b)->format('%a') + 1;
    }

    private static function sumarDias(string $fecha, int $dias): string
    {
        $d = new DateTime($fecha);
        $d->modify(($dias >= 0 ? '+' : '') . $dias . ' day');

        return $d->format('Y-m-d');
    }
}
