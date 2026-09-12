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
}
