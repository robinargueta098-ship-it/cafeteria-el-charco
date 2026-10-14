<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Exportación a CSV pensada para Excel: UTF-8 con BOM, coma como separador, cabeceras en español, montos como número
 * con dos decimales y SIN símbolo (para que se puedan sumar), y protección contra inyección de fórmulas
 * (una celda de texto que empiece con = + - @ o tabulador/retorno se escribe con un apóstrofo delante).
 *
 * Se escribe en streaming con fputcsv: no carga todo el resultado en memoria.
 * Compatible con PHP 7.4.
 */
class Csv
{
    /**
     * Tipos de columna: texto | entero | dinero (centavos) | porcentaje (diezmilésimas de %) | fecha.
     *
     * @param array<int,array{clave:string,etiqueta:string,tipo?:string}> $columnas
     * @param iterable<array<string,mixed>> $filas
     */
    public static function enviar(string $nombreArchivo, array $columnas, $filas): void
    {
        $nombreArchivo = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $nombreArchivo);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: no-store');

        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");   // BOM: Excel reconoce UTF-8 (tildes y ñ)

        fputcsv($salida, array_map(function (array $c) { return self::celdaTexto($c['etiqueta']); }, $columnas));

        foreach ($filas as $fila) {
            $linea = [];
            foreach ($columnas as $c) {
                $linea[] = self::celda(isset($fila[$c['clave']]) ? $fila[$c['clave']] : null, isset($c['tipo']) ? $c['tipo'] : 'texto');
            }
            fputcsv($salida, $linea);
        }

        fclose($salida);
    }

    /** @param mixed $valor */
    public static function celda($valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        switch ($tipo) {
            case 'dinero':
                return Dinero::decimal((int) $valor);
            case 'entero':
                return (string) (int) $valor;
            case 'porcentaje':
                return Dinero::decimal((int) $valor);   // diezmilésimas → "12.50"
            default:
                return self::celdaTexto((string) $valor);
        }
    }

    /** Evita que Excel interprete el texto como una fórmula. */
    public static function celdaTexto(string $texto): string
    {
        if ($texto !== '' && strpos("=+-@\t\r", $texto[0]) !== false) {
            return "'" . $texto;
        }

        return $texto;
    }
}
