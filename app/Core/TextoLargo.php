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
     * Separa un texto (el formato de un solo campo) en las partes de la estructura editable: introducción,
     * subtítulo, tarjetas y frase de cierre. Usa el MISMO analizador que formatear(), así que lo que antes
     * se mostraba como subtítulo / tarjetas / cierre queda en su campo correspondiente.
     *
     * Devuelve null si el texto no cabe en esa estructura (varios subtítulos o listas, párrafos después
     * de la lista, más de $maxTarjetas elementos…): en ese caso quien llama debe conservar el texto tal cual.
     *
     * @return array{intro:string,subtitulo:string,tarjetas:array<int,array{titulo:string,descripcion:string}>,cierre:string}|null
     */
    public static function estructurar(string $texto, int $maxTarjetas = 6): ?array
    {
        $bloques = self::bloques($texto);
        $cierre = '';

        $ultimo = count($bloques) - 1;
        if ($ultimo >= 1 && $bloques[$ultimo]['tipo'] === 'parrafo'
            && count($bloques[$ultimo]['lineas']) === 1
            && mb_strlen($bloques[$ultimo]['lineas'][0]) <= self::CIERRE_MAX_CARACTERES) {
            $cierre = $bloques[$ultimo]['lineas'][0];
            array_pop($bloques);
        }

        $intro = [];
        $subtitulo = '';
        $tarjetas = [];
        $fase = 0;   // 0 = introducción, 1 = ya hubo subtítulo, 2 = ya hubo lista
        foreach ($bloques as $bloque) {
            if ($bloque['tipo'] === 'parrafo' && $fase === 0) {
                $intro[] = implode("\n", $bloque['lineas']);
            } elseif ($bloque['tipo'] === 'subtitulo' && $fase === 0) {
                $subtitulo = rtrim(substr($bloque['lineas'][0], 0, -1));   // sin los ":" finales: se agregan al mostrar
                $fase = 1;
            } elseif ($bloque['tipo'] === 'lista' && $fase <= 1) {
                foreach ($bloque['lineas'] as $linea) {
                    $tarjetas[] = self::partirElemento($linea);
                }
                $fase = 2;
            } else {
                return null;
            }
        }

        if (count($tarjetas) > $maxTarjetas) {
            return null;
        }

        return ['intro' => implode("\n\n", $intro), 'subtitulo' => $subtitulo, 'tarjetas' => $tarjetas, 'cierre' => $cierre];
    }

    /**
     * Muestra la estructura editable con el mismo HTML y las mismas clases que formatear().
     *
     * @param array{intro?:string,subtitulo?:string,tarjetas?:array<int,array{titulo?:string,descripcion?:string}>,cierre?:string} $e
     */
    public static function formatearEstructura(array $e): string
    {
        $html = '';

        $parrafo = [];
        $cerrarParrafo = function () use (&$html, &$parrafo): void {
            if ($parrafo !== []) {
                $html .= '<p class="texto-largo-parrafo">' . implode("<br>\n", array_map([self::class, 'e'], $parrafo)) . "</p>\n";
                $parrafo = [];
            }
        };
        foreach (preg_split('/\R/u', (string) ($e['intro'] ?? '')) ?: [] as $linea) {
            $linea = self::recortar($linea);
            if ($linea === '') {
                $cerrarParrafo();
            } else {
                $parrafo[] = $linea;
            }
        }
        $cerrarParrafo();

        $subtitulo = self::recortar((string) ($e['subtitulo'] ?? ''));
        if ($subtitulo !== '') {
            $html .= '<h3 class="texto-largo-subtitulo">' . self::e(self::conDosPuntos($subtitulo)) . "</h3>\n";
        }

        $items = '';
        foreach ((array) ($e['tarjetas'] ?? []) as $t) {
            $titulo = self::recortar((string) ($t['titulo'] ?? ''));
            $descripcion = self::recortar((string) ($t['descripcion'] ?? ''));
            if ($titulo === '') {
                continue;   // una tarjeta sin título se descarta
            }
            $items .= '<li class="texto-largo-item">'
                . ($descripcion !== '' ? '<strong>' . self::e(self::conDosPuntos($titulo)) . '</strong> ' . self::e($descripcion) : self::e($titulo))
                . '</li>';
        }
        if ($items !== '') {
            $html .= '<ul class="texto-largo-lista">' . $items . "</ul>\n";
        }

        $cierre = self::recortar((string) ($e['cierre'] ?? ''));
        if ($cierre !== '') {
            $html .= '<p class="texto-largo-cierre">' . self::e($cierre) . "</p>\n";
        }

        return $html;
    }

    private static function conDosPuntos(string $texto): string
    {
        return str_ends_with($texto, ':') ? $texto : $texto . ':';
    }

    /** "Encabezado: descripción" → título (sin los ":") y descripción; sin encabezado, todo va como título. */
    private static function partirElemento(string $linea): array
    {
        $pos = strpos($linea, ':');
        if ($pos !== false) {
            $encabezado = substr($linea, 0, $pos + 1);
            $descripcion = self::recortar(substr($linea, $pos + 1));
            if ($descripcion !== '' && mb_strlen($encabezado) <= self::ENCABEZADO_MAX_CARACTERES) {
                return ['titulo' => rtrim(substr($encabezado, 0, -1)), 'descripcion' => $descripcion];
            }
        }

        return ['titulo' => $linea, 'descripcion' => ''];
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
