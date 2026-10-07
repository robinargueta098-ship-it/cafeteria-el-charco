<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Da formato de lectura a un texto largo guardado como texto simple (por ejemplo "Sobre nosotros"),
 * sin cambiar ni una palabra: solo decide cómo se ve. Pensado para reutilizarse en otras secciones de
 * texto largo (descripción de la cafetería, etc.).
 *
 * Reglas (por línea; las líneas en blanco separan párrafos):
 *  - Una línea que empieza con "-" (con o sin espacio después) es un elemento de lista; el guion deja de
 *    verse. Si es "Encabezado: descripción", el encabezado (con los dos puntos) va en negrita.
 *  - Una línea que termina en ":" y no empieza con guion es un subtítulo. Una línea con guion que SOLO
 *    trae un encabezado terminado en ":" (sin descripción) también se muestra como subtítulo: introduce
 *    la lista que sigue y como tarjeta vacía no tendría sentido.
 *  - La última línea del texto, si es una sola línea corta y el texto no termina en una lista, se muestra
 *    como frase de cierre (solo cuando el texto tiene más de un bloque).
 *  - Todo lo demás son párrafos; un salto de línea suelto dentro de un párrafo se conserva como <br>.
 *
 * El texto se escapa con htmlspecialchars ANTES de dar formato: nunca sale HTML crudo del usuario.
 * Devuelve solo los elementos (sin contenedor), para que cada vista los acomode en su propia estructura.
 */
class TextoLargo
{
    /** Una línea más larga que esto no se destaca como frase de cierre (sería un párrafo normal). */
    private const CIERRE_MAX_CARACTERES = 220;

    /** Un "Encabezado:" más largo que esto no se trata como encabezado (es una oración con dos puntos). */
    private const ENCABEZADO_MAX_CARACTERES = 80;

    public static function formatear(string $texto): string
    {
        $bloques = self::bloques($texto);
        if ($bloques === []) {
            return '';
        }

        $ultimo = count($bloques) - 1;
        $html = '';

        foreach ($bloques as $i => $bloque) {
            switch ($bloque['tipo']) {
                case 'subtitulo':
                    $html .= '<h3 class="texto-largo-subtitulo">' . self::e($bloque['lineas'][0]) . "</h3>\n";
                    break;

                case 'lista':
                    $html .= '<ul class="texto-largo-lista">';
                    foreach ($bloque['lineas'] as $linea) {
                        $html .= '<li class="texto-largo-item">' . self::elemento($linea) . '</li>';
                    }
                    $html .= "</ul>\n";
                    break;

                default:
                    $esCierre = $i === $ultimo
                        && count($bloques) > 1
                        && count($bloque['lineas']) === 1
                        && mb_strlen($bloque['lineas'][0]) <= self::CIERRE_MAX_CARACTERES;

                    $contenido = implode("<br>\n", array_map([self::class, 'e'], $bloque['lineas']));
                    $html .= $esCierre
                        ? '<p class="texto-largo-cierre">' . $contenido . "</p>\n"
                        : '<p class="texto-largo-parrafo">' . $contenido . "</p>\n";
            }
        }

        return $html;
    }

    /**
     * @return array<int,array{tipo:string,lineas:array<int,string>}>
     */
    private static function bloques(string $texto): array
    {
        $bloques = [];
        $parrafo = [];   // líneas consecutivas de un mismo párrafo
        $lista = [];     // elementos consecutivos de una misma lista

        $cerrarParrafo = function () use (&$bloques, &$parrafo): void {
            if ($parrafo !== []) {
                $bloques[] = ['tipo' => 'parrafo', 'lineas' => $parrafo];
                $parrafo = [];
            }
        };
        $cerrarLista = function () use (&$bloques, &$lista): void {
            if ($lista !== []) {
                $bloques[] = ['tipo' => 'lista', 'lineas' => $lista];
                $lista = [];
            }
        };

        foreach (preg_split('/\R/u', $texto) ?: [] as $cruda) {
            $linea = self::recortar($cruda);

            if ($linea === '') {
                $cerrarParrafo();
                $cerrarLista();
                continue;
            }

            if (str_starts_with($linea, '-')) {
                $contenido = self::recortar(substr($linea, 1));
                if ($contenido === '') {
                    continue;   // un guion solo, sin nada, no aporta nada que mostrar
                }

                $cerrarParrafo();
                if (str_ends_with($contenido, ':')) {
                    // Encabezado de lista sin descripción ("-Lo que nos distingue:"): subtítulo.
                    $cerrarLista();
                    $bloques[] = ['tipo' => 'subtitulo', 'lineas' => [$contenido]];
                } else {
                    $lista[] = $contenido;
                }
                continue;
            }

            $cerrarLista();

            if (str_ends_with($linea, ':')) {
                $cerrarParrafo();
                $bloques[] = ['tipo' => 'subtitulo', 'lineas' => [$linea]];
                continue;
            }

            $parrafo[] = $linea;
        }

        $cerrarParrafo();
        $cerrarLista();

        return $bloques;
    }

    /** "Encabezado: descripción" → encabezado en negrita (con los dos puntos) y descripción normal. */
    private static function elemento(string $linea): string
    {
        $pos = strpos($linea, ':');
        if ($pos !== false) {
            $encabezado = substr($linea, 0, $pos + 1);
            $descripcion = self::recortar(substr($linea, $pos + 1));

            if ($descripcion !== '' && mb_strlen($encabezado) <= self::ENCABEZADO_MAX_CARACTERES) {
                return '<strong>' . self::e($encabezado) . '</strong> ' . self::e($descripcion);
            }
        }

        return self::e($linea);
    }

    /** Quita espacios y los caracteres invisibles (ancho cero) que deja copiar y pegar, solo en los bordes de la línea. */
    private static function recortar(string $linea): string
    {
        return trim((string) preg_replace('/^[\s\x{200B}\x{FEFF}\x{00A0}]+|[\s\x{200B}\x{FEFF}\x{00A0}]+$/u', '', $linea));
    }

    private static function e(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }
}
