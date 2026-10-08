<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contenido de la sección "Sobre nosotros": introducción, subtítulo, tarjetas, frase de cierre y foto.
 *
 * Se guarda como UNA estructura (JSON) en la clave `sobre_nosotros_estructura` de configuracion_sistema,
 * más la foto propia en `sobre_nosotros_foto` (+ `sobre_nosotros_foto_pie`). El texto anterior de un solo
 * campo (`sobre_nosotros_texto`) NO se borra ni se modifica nunca: mientras no se guarde la estructura
 * desde el panel, el sitio sigue mostrándolo con el formateador de siempre (TextoLargo::formatear) y el
 * panel lo separa automáticamente (TextoLargo::estructurar) para rellenar los campos.
 */
class SobreNosotros
{
    public const CLAVE_ESTRUCTURA = 'sobre_nosotros_estructura';
    public const CLAVE_FOTO = 'sobre_nosotros_foto';
    public const CLAVE_FOTO_PIE = 'sobre_nosotros_foto_pie';
    public const CLAVE_TEXTO_ANTERIOR = 'sobre_nosotros_texto';

    public const MAX_TARJETAS = 6;
    public const MAX_INTRO = 2000;
    public const MAX_SUBTITULO = 80;
    public const MAX_TITULO = 80;
    public const MAX_DESCRIPCION = 500;
    public const MAX_CIERRE = 250;
    public const MAX_PIE = 150;

    /** Campos del formulario del panel (planos, no arreglos, para que el borrador por token vencido los conserve completos). */
    public const CAMPO_INTRO = 'nosotros_intro';
    public const CAMPO_SUBTITULO = 'nosotros_subtitulo';
    public const CAMPO_CIERRE = 'nosotros_cierre';
    public const PREFIJO_TITULO = 'nosotros_titulo_';
    public const PREFIJO_DESCRIPCION = 'nosotros_desc_';

    /**
     * Estructura para mostrar en el panel: la guardada; si todavía no hay, la que sale de separar el texto anterior.
     *
     * @param array<string,string> $config
     * @return array{intro:string,subtitulo:string,tarjetas:array<int,array{titulo:string,descripcion:string}>,cierre:string,origen:string}
     */
    public static function estructura(array $config): array
    {
        $guardada = self::leerGuardada($config);
        if ($guardada !== null) {
            return $guardada + ['origen' => 'guardada'];
        }

        $anterior = trim((string) ($config[self::CLAVE_TEXTO_ANTERIOR] ?? ''));
        if ($anterior !== '') {
            $separada = TextoLargo::estructurar($anterior, self::MAX_TARJETAS);
            if ($separada !== null) {
                return $separada + ['origen' => 'texto_anterior'];
            }

            // No cabe en la estructura: todo el texto va a la introducción (no se pierde ninguna palabra).
            return ['intro' => $anterior, 'subtitulo' => '', 'tarjetas' => [], 'cierre' => '', 'origen' => 'texto_anterior_libre'];
        }

        return ['intro' => '', 'subtitulo' => '', 'tarjetas' => [], 'cierre' => '', 'origen' => 'vacia'];
    }

    /**
     * HTML (ya escapado) del contenido de la sección, o '' si no hay nada que mostrar.
     *
     * @param array<string,string> $config
     */
    public static function html(array $config): string
    {
        $guardada = self::leerGuardada($config);
        if ($guardada !== null) {
            return TextoLargo::formatearEstructura($guardada);
        }

        // Todavía no se ha guardado la estructura desde el panel: se usa el texto anterior, igual que antes.
        return TextoLargo::formatear((string) ($config[self::CLAVE_TEXTO_ANTERIOR] ?? ''));
    }

    /**
     * Foto de la sección. Prioridad: la propia (Configuración) → la primera de la galería → ninguna.
     *
     * @param array<string,string> $config
     * @param array<int,array<string,mixed>> $galeria
     * @return array{url:string,alt:string,pie:string,propia:bool}|null
     */
    public static function foto(array $config, array $galeria, string $nombreSitio): ?array
    {
        $propia = basename((string) ($config[self::CLAVE_FOTO] ?? ''));
        if ($propia !== '' && is_file(UPLOADS_PATH . '/sitio/' . $propia)) {
            return [
                'url'    => UPLOADS_URL . '/sitio/' . $propia,
                'alt'    => $nombreSitio,
                'pie'    => trim((string) ($config[self::CLAVE_FOTO_PIE] ?? '')),   // texto visible debajo de la foto
                'propia' => true,
            ];
        }

        $primera = $galeria[0] ?? null;
        if ($primera !== null && !empty($primera['imagen'])) {
            return [
                'url'    => UPLOADS_URL . '/galeria/' . $primera['imagen'],
                'alt'    => (string) (($primera['titulo'] ?? '') ?: $nombreSitio),
                'pie'    => trim((string) ($config[self::CLAVE_FOTO_PIE] ?? '')),
                'propia' => false,
            ];
        }

        return null;
    }

    /** ¿Vino algún campo de la estructura en este envío? (si no, la sección no se toca) */
    public static function hayEnvio(array $post): bool
    {
        if (array_key_exists(self::CAMPO_INTRO, $post) || array_key_exists(self::CAMPO_SUBTITULO, $post) || array_key_exists(self::CAMPO_CIERRE, $post)) {
            return true;
        }
        for ($i = 1; $i <= self::MAX_TARJETAS; $i++) {
            if (array_key_exists(self::PREFIJO_TITULO . $i, $post) || array_key_exists(self::PREFIJO_DESCRIPCION . $i, $post)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Arma la estructura nueva a partir del envío. Solo cambia lo que llegó: un campo ausente conserva lo
     * que ya había (actualización parcial). Una tarjeta sin título se descarta.
     *
     * @param array<string,mixed> $post
     * @param array{intro:string,subtitulo:string,tarjetas:array<int,array{titulo:string,descripcion:string}>,cierre:string} $actual
     * @return array{estructura:array{intro:string,subtitulo:string,tarjetas:array<int,array{titulo:string,descripcion:string}>,cierre:string},errores:array<int,string>}
     */
    public static function desdeEnvio(array $post, array $actual): array
    {
        $errores = [];
        $campos = [];

        $leer = function (string $clave, string $porDefecto, string $etiqueta) use ($post, &$errores): string {
            if (!array_key_exists($clave, $post)) {
                return $porDefecto;
            }
            if (!is_string($post[$clave])) {
                $errores[] = "El campo «{$etiqueta}» no tiene un formato válido.";
                return $porDefecto;
            }

            return str_replace(["\r\n", "\r"], "\n", trim($post[$clave]));
        };

        $intro = $leer(self::CAMPO_INTRO, $actual['intro'], 'Introducción');
        $subtitulo = $leer(self::CAMPO_SUBTITULO, $actual['subtitulo'], 'Subtítulo de las tarjetas');
        $cierre = $leer(self::CAMPO_CIERRE, $actual['cierre'], 'Frase de cierre');
        $campos += ['intro' => $intro, 'subtitulo' => $subtitulo, 'cierre' => $cierre];

        // Tarjetas: si llegó cualquier campo de tarjeta, la lista se reconstruye completa con lo enviado
        // (los lugares que no llegaron cuentan como vacíos); si no llegó ninguno, se conserva la actual.
        $hayTarjetas = false;
        for ($i = 1; $i <= self::MAX_TARJETAS; $i++) {
            if (array_key_exists(self::PREFIJO_TITULO . $i, $post) || array_key_exists(self::PREFIJO_DESCRIPCION . $i, $post)) {
                $hayTarjetas = true;
                break;
            }
        }

        $tarjetas = $actual['tarjetas'];
        if ($hayTarjetas) {
            $tarjetas = [];
            for ($i = 1; $i <= self::MAX_TARJETAS; $i++) {
                $titulo = $leer(self::PREFIJO_TITULO . $i, '', "Título de la tarjeta {$i}");
                $descripcion = $leer(self::PREFIJO_DESCRIPCION . $i, '', "Descripción de la tarjeta {$i}");
                if ($titulo === '') {
                    continue;   // sin título la tarjeta se descarta (aunque tenga descripción)
                }
                $campos["titulo_{$i}"] = $titulo;
                $campos["descripcion_{$i}"] = $descripcion;
                $tarjetas[] = ['titulo' => $titulo, 'descripcion' => $descripcion];
            }
        }

        $validador = new Validator($campos);
        $validador
            ->longitudMaxima('intro', 'Introducción', self::MAX_INTRO)
            ->longitudMaxima('subtitulo', 'Subtítulo de las tarjetas', self::MAX_SUBTITULO)
            ->longitudMaxima('cierre', 'Frase de cierre', self::MAX_CIERRE);
        for ($i = 1; $i <= self::MAX_TARJETAS; $i++) {
            $validador
                ->longitudMaxima("titulo_{$i}", "Título de la tarjeta {$i}", self::MAX_TITULO)
                ->longitudMaxima("descripcion_{$i}", "Descripción de la tarjeta {$i}", self::MAX_DESCRIPCION);
        }

        return [
            'estructura' => ['intro' => $intro, 'subtitulo' => $subtitulo, 'tarjetas' => $tarjetas, 'cierre' => $cierre],
            'errores'    => array_merge($errores, $validador->errores()),
        ];
    }

    /** JSON que se guarda en la clave de configuración. */
    public static function aJson(array $estructura): string
    {
        return (string) json_encode([
            'intro'     => (string) $estructura['intro'],
            'subtitulo' => (string) $estructura['subtitulo'],
            'tarjetas'  => array_values($estructura['tarjetas']),
            'cierre'    => (string) $estructura['cierre'],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** @return array{intro:string,subtitulo:string,tarjetas:array<int,array{titulo:string,descripcion:string}>,cierre:string}|null */
    private static function leerGuardada(array $config): ?array
    {
        $json = trim((string) ($config[self::CLAVE_ESTRUCTURA] ?? ''));
        if ($json === '') {
            return null;
        }

        $datos = json_decode($json, true);
        if (!is_array($datos)) {
            return null;   // JSON dañado: se cae al texto anterior en vez de mostrar nada
        }

        $tarjetas = [];
        foreach ((array) ($datos['tarjetas'] ?? []) as $t) {
            if (is_array($t) && trim((string) ($t['titulo'] ?? '')) !== '') {
                $tarjetas[] = ['titulo' => (string) $t['titulo'], 'descripcion' => (string) ($t['descripcion'] ?? '')];
            }
        }

        return [
            'intro'     => (string) ($datos['intro'] ?? ''),
            'subtitulo' => (string) ($datos['subtitulo'] ?? ''),
            'tarjetas'  => array_slice($tarjetas, 0, self::MAX_TARJETAS),
            'cierre'    => (string) ($datos['cierre'] ?? ''),
        ];
    }
}
