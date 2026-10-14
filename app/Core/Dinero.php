<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Dinero SIEMPRE en centavos enteros (BIGINT en la base de datos, int en PHP). Nada de float para montos:
 * un precio como "38.36" llega de la base como cadena y se convierte aquí con aritmética de enteros.
 *
 * Una sola función de formato ("Q1,234.50") para pantallas, mensajes y reportes, y otra para CSV ("1234.50",
 * sin símbolo, para que Excel pueda sumar).
 */
class Dinero
{
    /**
     * "38.36" -> 3836. Acepta cadenas decimales ("12", "12.5", "12.345" se redondea half-up al centavo),
     * enteros (quetzales) y floats (se pasan por number_format antes, nunca se multiplican por 100).
     *
     * @param string|int|float|null $valor
     */
    public static function aCentavos($valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        if (is_int($valor)) {
            return $valor * 100;
        }
        if (is_float($valor)) {
            $valor = number_format($valor, 4, '.', '');
        }

        $texto = trim((string) $valor);
        if (!preg_match('/^(\d{1,12})(?:\.(\d+))?$/', $texto, $m)) {
            throw new InvalidArgumentException('Monto inválido: ' . $texto);
        }

        $decimales = (isset($m[2]) ? $m[2] : '') . '000';
        $centavos = ((int) $m[1]) * 100 + (int) substr($decimales, 0, 2);
        if ((int) $decimales[2] >= 5) {
            $centavos++;   // redondeo half-up
        }

        return $centavos;
    }

    /** 123450 -> "Q1,234.50" */
    public static function formato(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $c = abs($centavos);

        return $signo . 'Q' . number_format(intdiv($c, 100), 0, '.', ',') . '.' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 123450 -> "1234.50" (para CSV: sin símbolo ni separador de miles) */
    public static function decimal(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $c = abs($centavos);

        return $signo . intdiv($c, 100) . '.' . str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }
}
