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
    /** Taza de café sencilla (trazo, hereda el color): relleno de las fotos que aún no hay y adorno de avisos. */
    public const ICONO_TAZA = '<svg class="icono-taza" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8 3.5c-.8 1 .8 1.6 0 2.8M12 3.5c-.8 1 .8 1.6 0 2.8"/></svg>';

    public const ICONO_TAZA_TITULO = '<svg class="icono-taza icono-taza-titulo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9z"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/><path d="M8 3.5c-.8 1 .8 1.6 0 2.8M12 3.5c-.8 1 .8 1.6 0 2.8"/></svg>';

    public static function pendiente(string $etiqueta): string
    {
        // Solo lo ve quien tiene sesión en el panel: el cliente no debe ver avisos internos.
        if (empty($_SESSION['usuario_id'])) {
            return '';
        }

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

        return '<div class="foto-pendiente" aria-hidden="true">' . self::ICONO_TAZA . '</div>';
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

        switch ($promocion['tipo']) {
            case 'descuento_porcentaje':
                return $valor . '% de descuento';
            case 'descuento_monto':
                return 'Q' . $valor . ' de descuento';
            case 'dos_por_uno':
                return '2x1';
            case 'producto_gratis':
                return 'Producto gratis';
            case 'personalizada':
                return trim((string) ($promocion['etiqueta_personalizada'] ?? ''));   // texto libre del administrador
            default:
                return '';
        }
    }

    /** "Frappés" -> "Frappé", "Batidos" -> "Batido" (singular sencillo para armar el nombre del pedido). */
    private static function singular(string $palabra): string
    {
        $palabra = trim($palabra);
        return mb_strlen($palabra) > 3 && str_ends_with($palabra, 's') ? mb_substr($palabra, 0, -1) : $palabra;
    }

    /**
     * Variantes que se pueden pedir de un producto, con el nombre COMPLETO que va en el mensaje
     * de WhatsApp para que no haya ambigüedad de qué preparar: el mismo "Fresa" existe en
     * Smoothies, Lassie y Sodas italianas, y un frappé tiene versión con café y sin café.
     *
     *  - Producto con segundo precio en una categoría "A / B" (ej. "Frappés / Batidos"):
     *    "Frappé de Moka" (precio normal) y "Batido de Moka" (segundo precio).
     *  - Cualquier otro: "Fresa (Smoothies)"; con segundo precio: "Fresa (Smoothies, Sin café)".
     *
     * @param array<string,mixed> $producto fila de `productos`
     * @return array<int,array{clave:string,nombre:string,precio:float,etiqueta:string,corta:string}>
     */
    public static function variantesPedido(array $producto, string $categoria): array
    {
        $id = (string) $producto['id'];
        $nombre = trim((string) $producto['nombre']);
        $categoria = trim($categoria);
        $tieneAlt = !empty($producto['precio_alt']);
        $etiquetaAlt = trim((string) ($producto['etiqueta_precio_alt'] ?? ''));

        if ($tieneAlt && str_contains($categoria, '/')) {
            $partes = array_map('trim', explode('/', $categoria));
            $base = self::singular($partes[0]);
            $alterna = self::singular($partes[1] ?? $etiquetaAlt);

            return [
                ['clave' => $id, 'nombre' => "{$base} de {$nombre}", 'precio' => (float) $producto['precio'], 'etiqueta' => $base, 'corta' => $base],
                ['clave' => $id . '-alt', 'nombre' => "{$alterna} de {$nombre}", 'precio' => (float) $producto['precio_alt'],
                 'etiqueta' => $etiquetaAlt !== '' ? "{$alterna} ({$etiquetaAlt})" : $alterna, 'corta' => $alterna],
            ];
        }

        $sufijo = $categoria !== '' ? " ({$categoria})" : '';
        $variantes = [['clave' => $id, 'nombre' => $nombre . $sufijo, 'precio' => (float) $producto['precio'], 'etiqueta' => '', 'corta' => 'Normal']];

        if ($tieneAlt) {
            $detalle = $etiquetaAlt !== '' ? $etiquetaAlt : 'Opción 2';
            $variantes[0]['etiqueta'] = 'Normal';
            $variantes[] = [
                'clave' => $id . '-alt',
                'nombre' => $nombre . ($categoria !== '' ? " ({$categoria}, {$detalle})" : " ({$detalle})"),
                'precio' => (float) $producto['precio_alt'],
                'etiqueta' => $detalle,
                'corta' => $detalle,
            ];
        }

        return $variantes;
    }

    /**
     * Logo oficial de WhatsApp como SVG en línea (trazo de Simple Icons,
     * simpleicons.org, licencia CC0 — sin depender de ningún servidor
     * externo, igual que los íconos de redes de footer_publico.php).
     * Se usa en tres botones (nav, portada, footer): centralizado aquí
     * para no repetir el mismo SVG tres veces. Decorativo (aria-hidden):
     * el texto del botón sigue siendo lo que anuncia un lector de pantalla.
     */
    public static function iconoWhatsapp(): string
    {
        return '<svg class="icono-boton" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
    }
}
