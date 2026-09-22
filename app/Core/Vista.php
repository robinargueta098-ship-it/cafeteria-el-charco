<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Helpers de presentación para el sitio público (docs/negocio.md: no
 * inventar datos reales del negocio que todavía no se han
 * proporcionado — dirección, horario, fotos, etc. — se marcan con un
 * placeholder claramente visible en vez de rellenarlos con algo falso).
 */
class Vista
{
    /**
     * URL de un archivo estático (CSS/JS) con su fecha de modificación como
     * versión: `/assets/css/main.css?v=1758480000`. Cada vez que el archivo
     * cambia, la URL cambia, así el navegador nunca mezcla HTML nuevo con un
     * CSS/JS viejo guardado en su caché (ej. un botón nuevo que se ve porque
     * le falta la regla que lo oculta). Devuelve la URL SIN escapar: quien la
     * imprima debe pasarla por htmlspecialchars().
     *
     * @param string $rutaUrl ruta pública, con "/" inicial (ej. '/assets/css/main.css')
     */
    public static function asset(string $rutaUrl): string
    {
        $archivo = BASE_PATH . '/public' . $rutaUrl;

        return is_file($archivo) ? $rutaUrl . '?v=' . filemtime($archivo) : $rutaUrl;
    }

    /**
     * Insignia de "falta este dato del negocio" — para hechos únicos
     * (dirección, horario, texto "sobre nosotros", reseña de Google,
     * foto de portada). Deliberadamente llamativa: es una tarea
     * pendiente para el dueño del negocio, no un detalle de diseño.
     */
    public static function pendiente(string $etiqueta): string
    {
        return '<span class="marcador-pendiente">[PENDIENTE: '
            . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8')
            . ']</span>';
    }

    /**
     * Imagen de un producto/foto de galería, o un marcador discreto de
     * "foto pendiente" si todavía no se ha subido una. A diferencia de
     * pendiente(), este es para el caso normal y esperado de un
     * catálogo que se va completando con fotos con el tiempo — no se
     * repite en mayúsculas ni en rojo en cada una de las tarjetas.
     */
    public static function fotoOMarcador(?string $archivo, string $carpetaUrl, string $alt): string
    {
        if ($archivo !== null && $archivo !== '') {
            $ruta = htmlspecialchars($carpetaUrl . '/' . $archivo, ENT_QUOTES, 'UTF-8');
            $altEscapado = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

            return "<img src=\"{$ruta}\" alt=\"{$altEscapado}\" loading=\"lazy\" decoding=\"async\">";
        }

        return '<div class="foto-pendiente" aria-hidden="true"><span>📷 Foto pendiente</span></div>';
    }

    private const MESES_ES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /**
     * "2026-12-20" -> "20 de diciembre" (o "20 de diciembre de 2027" si no
     * es el año en curso). Usado donde se muestra una fecha de vigencia —
     * promociones, temporadas — al cliente del sitio público.
     */
    public static function fechaLarga(string $fechaIso): string
    {
        [$anio, $mes, $dia] = explode('-', $fechaIso);
        $texto = ((int) $dia) . ' de ' . self::MESES_ES[(int) $mes];

        return $anio === date('Y') ? $texto : $texto . ' de ' . $anio;
    }

    /**
     * Texto corto del beneficio de una promoción (tabla `promociones`, no
     * confundir con las recompensas de lealtad): "20% de descuento",
     * "Q10 de descuento", "2x1", "Producto gratis".
     *
     * @param array{tipo:string,valor:mixed} $promocion
     */
    public static function etiquetaPromocion(array $promocion): string
    {
        $valor = $promocion['valor'] !== null
            ? rtrim(rtrim(number_format((float) $promocion['valor'], 2), '0'), '.')
            : null;

        return match ($promocion['tipo']) {
            'descuento_porcentaje' => $valor . '% de descuento',
            'descuento_monto'      => 'Q' . $valor . ' de descuento',
            'dos_por_uno'          => '2x1',
            'producto_gratis'      => 'Producto gratis',
            default                => '',
        };
    }
}
