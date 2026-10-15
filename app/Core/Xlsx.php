<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use ZipArchive;

/**
 * Exportación a Excel (.xlsx) sin librerías externas: un libro de una hoja, con encabezado en negrita, fila fija,
 * filtros, anchos de columna y montos como NÚMEROS con dos decimales (se pueden sumar). El texto se escribe como
 * texto literal (inlineStr), así que un valor que empiece con = + - @ nunca se interpreta como fórmula.
 *
 * Mismas columnas y tipos que Csv::enviar (texto | entero | dinero | porcentaje | fecha). Requiere la extensión zip.
 * Compatible con PHP 7.4.
 */
class Xlsx
{
    /**
     * @param array<int,array{clave:string,etiqueta:string,tipo?:string}> $columnas
     * @param iterable<array<string,mixed>> $filas
     */
    public static function enviar(string $nombreArchivo, string $hoja, array $columnas, $filas): void
    {
        $nombreArchivo = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $nombreArchivo);
        $contenido = self::construir($hoja, $columnas, $filas);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . strlen($contenido));
        header('Cache-Control: no-store');

        echo $contenido;
    }

    /**
     * @param array<int,array{clave:string,etiqueta:string,tipo?:string}> $columnas
     * @param iterable<array<string,mixed>> $filas
     */
    public static function construir(string $hoja, array $columnas, $filas): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión zip de PHP no está disponible.');
        }

        $anchos = [];
        $xml = '<row r="1">';
        $n = 1;

        // Encabezado (estilo 1 = negrita con fondo)
        foreach ($columnas as $i => $c) {
            $xml .= self::celdaTexto(self::referencia($i, 1), (string) $c['etiqueta'], 1);
            $anchos[$i] = mb_strlen((string) $c['etiqueta']) + 2;
        }
        $xml .= '</row>';

        foreach ($filas as $fila) {
            $n++;
            $xml .= '<row r="' . $n . '">';
            foreach ($columnas as $i => $c) {
                $valor = isset($fila[$c['clave']]) ? $fila[$c['clave']] : null;
                $tipo = isset($c['tipo']) ? $c['tipo'] : 'texto';
                $ref = self::referencia($i, $n);

                if ($valor === null || $valor === '') {
                    continue;
                }
                if ($tipo === 'dinero' || $tipo === 'porcentaje') {
                    $texto = Dinero::decimal((int) $valor);
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . $texto . '</v></c>';
                } elseif ($tipo === 'entero') {
                    $texto = (string) (int) $valor;
                    $xml .= '<c r="' . $ref . '" s="3"><v>' . $texto . '</v></c>';
                } else {
                    $texto = (string) $valor;
                    $xml .= self::celdaTexto($ref, $texto, 0);
                }
                $anchos[$i] = max($anchos[$i], min(60, mb_strlen($texto) + 2));
            }
            $xml .= '</row>';
        }

        $ultimaCol = self::letras(max(0, count($columnas) - 1));
        $cols = '';
        foreach ($anchos as $i => $a) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(10, $a) . '" customWidth="1"/>';
        }

        $cabecera = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $hojaXml = $cabecera
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>' . $cols . '</cols>'
            . '<sheetData>' . $xml . '</sheetData>'
            . '<autoFilter ref="A1:' . $ultimaCol . $n . '"/>'
            . '</worksheet>';

        $nombreHoja = self::textoXml(mb_substr((string) preg_replace('/[\[\]\*\?\/\\\\:]/u', ' ', $hoja), 0, 31));

        $estilos = $cabecera
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4ECDF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs></styleSheet>';

        $archivo = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($archivo, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }
        $zip->addFromString('[Content_Types].xml', $cabecera
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', $cabecera
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', $cabecera
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . ($nombreHoja !== '' ? $nombreHoja : 'Reporte') . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $cabecera
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $estilos);
        $zip->addFromString('xl/worksheets/sheet1.xml', $hojaXml);
        $zip->close();

        $bytes = (string) file_get_contents($archivo);
        @unlink($archivo);

        return $bytes;
    }

    private static function celdaTexto(string $ref, string $texto, int $estilo): string
    {
        return '<c r="' . $ref . '" t="inlineStr"' . ($estilo > 0 ? ' s="' . $estilo . '"' : '') . '><is><t xml:space="preserve">'
            . self::textoXml($texto) . '</t></is></c>';
    }

    private static function textoXml(string $texto): string
    {
        // Quita caracteres de control que XML no admite y escapa lo demás.
        $texto = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $texto);

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function letras(int $indice): string
    {
        $s = '';
        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    private static function referencia(int $col, int $fila): string
    {
        return self::letras($col) . $fila;
    }
}
