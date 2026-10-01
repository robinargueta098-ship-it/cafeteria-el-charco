<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\ImagenOptimizada;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Configuracion;
use App\Models\HorarioAtencion;
use App\Models\PortadaImagen;
use App\Services\AuditoriaService;

class ConfiguracionController extends Controller
{
    /** Claves editables desde este formulario (evita que alguien inyecte una clave nueva arbitraria). */
    private const CLAVES_EDITABLES = [
        'nombre_sitio', 'logo', 'direccion_texto', 'frase_hero', 'descripcion_corta',
        'whatsapp_numero', 'boton_whatsapp_texto', 'whatsapp_flotante_texto',
        'instagram_usuario', 'tiktok_usuario',
        'facebook_nombre', 'facebook_url',
        'umbral_lealtad', 'google_maps_embed', 'mapa_coordenadas', 'google_calificacion',
        'google_calificacion_url', 'sobre_nosotros_texto',
    ];

    /**
     * Se sube como archivo, no se escribe a mano — se maneja aparte del resto.
     * (Las imágenes del fondo de la portada YA NO están aquí: viven en su
     * propia tabla y las administra PortadaController.)
     */
    private const CLAVES_ARCHIVO = ['logo'];

    public function mostrar(): void
    {
        $this->requerirRol(1);

        $this->vistaFormulario((new Configuracion())->obtenerTodas(), []);
    }

    /**
     * Pantalla de Configuración: el formulario grande de ajustes + la
     * tarjeta aparte de "Imágenes de portada" (que necesita sus propios
     * formularios, y HTML no permite formularios anidados).
     *
     * @param array<string,string> $config
     * @param array<int,string> $errores
     * @param array<int,array<string,mixed>>|null $horarios si viene null se lee de la
     *        base (pantalla normal); si el formulario tuvo un error se pasa lo que el
     *        administrador acababa de escribir, para no hacerlo repetir el horario.
     */
    private function vistaFormulario(array $config, array $errores, ?array $horarios = null): void
    {
        $portada = new PortadaImagen();
        $cupo = PortadaController::MAX_IMAGENES - $portada->contar();

        $this->vista('admin/configuracion/formulario', [
            'seccionActiva'   => 'configuracion',
            'config'          => $config,
            'horarios'        => $horarios ?? (new HorarioAtencion())->listarTodos(),
            'errores'         => $errores,
            'portada'         => $portada->listar(),
            'portadaMax'      => PortadaController::MAX_IMAGENES,
            'portadaLote'     => max(0, min(PortadaController::MAX_ARCHIVOS_POR_LOTE, $cupo)),
            'portadaMaxBytes' => SubidaArchivo::TAMANO_MAXIMO_BYTES,
            'optimiza'        => ImagenOptimizada::disponible(),
        ]);
    }

    public function actualizar(): void
    {
        $this->requerirRol(1);

        $configModelo = new Configuracion();
        $configActual = $configModelo->obtenerTodas();

        $datos = [];
        foreach (self::CLAVES_EDITABLES as $clave) {
            if (in_array($clave, self::CLAVES_ARCHIVO, true)) {
                continue; // se resuelven abajo: o hay archivo nuevo, o se conserva el actual
            }
            $datos[$clave] = trim((string) ($_POST[$clave] ?? ''));
        }

        $horarioPost = $this->leerHorarioDesdePost();

        $errores = $this->validar($datos);
        $errores = array_merge($errores, $this->validarHorario($horarioPost));

        if ($errores !== []) {
            $this->vistaFormulario(
                $datos + array_intersect_key($configActual, array_flip(self::CLAVES_ARCHIVO)),
                $errores,
                $this->horarioPostComoFilas($horarioPost)
            );
            return;
        }

        $archivosNuevos = [];
        foreach (self::CLAVES_ARCHIVO as $clave) {
            $resultado = $this->procesarArchivo($clave, $configActual[$clave] ?? '');
            if (!$resultado['ok']) {
                $errores[] = $resultado['error'];
                continue;
            }
            $datos[$clave] = $resultado['valor'];
            if ($resultado['reemplazado']) {
                $archivosNuevos[] = $clave;
            }
        }

        if ($errores !== []) {
            $this->vistaFormulario($configActual, $errores, $this->horarioPostComoFilas($horarioPost));
            return;
        }

        $configModelo->actualizarMultiples($datos);
        $this->guardarHorario($horarioPost);

        AuditoriaService::registrar('configuracion_sistema', 0, 'editar', $configActual, $datos);

        Flash::exito('Configuración actualizada correctamente.');
        $this->redirigir('/admin/configuracion');
    }

    /**
     * @return array{ok:bool,valor?:string,reemplazado?:bool,error?:string}
     */
    private function procesarArchivo(string $clave, string $valorActual): array
    {
        if (($_FILES[$clave]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            // No se subió nada nuevo: se conserva lo que ya había.
            return ['ok' => true, 'valor' => $valorActual, 'reemplazado' => false];
        }

        $subida = SubidaArchivo::guardarImagen($_FILES[$clave], UPLOADS_PATH . '/sitio');
        if (!$subida['ok']) {
            return ['ok' => false, 'error' => $subida['error']];
        }

        if ($valorActual !== '') {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/sitio', $valorActual);
        }

        return ['ok' => true, 'valor' => $subida['nombre'], 'reemplazado' => true];
    }

    /**
     * Lee el horario de los 7 días desde $_POST, sin validar todavía.
     * Horario partido: "turno 1" (apertura/cierre) y un "turno 2" opcional
     * (apertura2/cierre2) para el día que tiene pausa (ej. 9:00-14:00 y
     * luego 16:00-21:00). Nombres de campo: horario[{dia}][apertura|cierre|apertura2|cierre2|abierto].
     *
     * @return array<int,array{cerrado:bool,apertura:?string,cierre:?string,apertura2:?string,cierre2:?string}>
     */
    private function leerHorarioDesdePost(): array
    {
        $dias = [];

        for ($dia = 0; $dia <= 6; $dia++) {
            // substr(...,0,5): defensa en el servidor, además del recorte ya
            // hecho al imprimir el value= del <input type="time"> (formulario.php)
            // — un navegador que reciba un value con segundos (como venían los
            // horarios ya guardados) los reenvía tal cual al guardar, y eso
            // no pasaba la validación de formato "HH:MM" más abajo, bloqueando
            // el guardado de TODO el formulario, no solo del horario.
            $campo = fn (string $nombre): ?string => (substr(trim((string) ($_POST['horario'][$dia][$nombre] ?? '')), 0, 5) ?: null);

            $dias[$dia] = [
                'cerrado'   => !isset($_POST['horario'][$dia]['abierto']),
                'apertura'  => $campo('apertura'),
                'cierre'    => $campo('cierre'),
                'apertura2' => $campo('apertura2'),
                'cierre2'   => $campo('cierre2'),
            ];
        }

        return $dias;
    }

    /**
     * Valida el horario de los 7 días. Un día "Cerrado" no se revisa (sus
     * horas, si quedaron escritas, simplemente se descartan al guardar).
     * Un día abierto necesita el turno 1 completo; el turno 2 es opcional
     * pero, si se usa, va completo — nunca una sola hora suelta.
     *
     * @param array<int,array{cerrado:bool,apertura:?string,cierre:?string,apertura2:?string,cierre2:?string}> $dias
     * @return array<int,string>
     */
    private function validarHorario(array $dias): array
    {
        $nombresDias = [0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado'];
        $formatoValido = fn (?string $hora): bool => $hora === null || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
        $errores = [];

        foreach ($dias as $dia => $d) {
            if ($d['cerrado']) {
                continue;
            }

            $nombre = $nombresDias[$dia];

            if (!$formatoValido($d['apertura']) || !$formatoValido($d['cierre']) || !$formatoValido($d['apertura2']) || !$formatoValido($d['cierre2'])) {
                $errores[] = "El horario del $nombre tiene una hora con un formato inválido.";
                continue;
            }

            if ($d['apertura'] === null || $d['cierre'] === null) {
                $errores[] = "Falta la hora de apertura o de cierre del $nombre (o márcalo como Cerrado).";
                continue;
            }

            if ($d['apertura'] >= $d['cierre']) {
                $errores[] = "En el $nombre la hora de apertura debe ser antes que la de cierre.";
            }

            // Turno 2: completo (las dos horas) o vacío del todo — nunca una sola.
            if (($d['apertura2'] === null) !== ($d['cierre2'] === null)) {
                $errores[] = "En el $nombre falta completar el segundo turno (o bórralo por completo).";
                continue;
            }

            if ($d['apertura2'] !== null && $d['cierre2'] !== null) {
                if ($d['apertura2'] >= $d['cierre2']) {
                    $errores[] = "En el $nombre, en el segundo turno la apertura debe ser antes que el cierre.";
                }
                if ($d['apertura2'] < $d['cierre']) {
                    $errores[] = "En el $nombre el segundo turno no puede empezar antes de que termine el primero.";
                }
            }
        }

        return $errores;
    }

    private function guardarHorario(array $dias): void
    {
        $modelo = new HorarioAtencion();

        foreach ($dias as $dia => $d) {
            $modelo->actualizarDia($dia, $d['apertura'], $d['cierre'], $d['apertura2'], $d['cierre2'], $d['cerrado']);
        }
    }

    /**
     * Convierte lo leído de $_POST a la misma forma de fila que devuelve
     * HorarioAtencion::listarTodos(), para poder reusar la vista tal cual
     * cuando hay que volver a mostrar el formulario con un error.
     *
     * @param array<int,array{cerrado:bool,apertura:?string,cierre:?string,apertura2:?string,cierre2:?string}> $dias
     * @return array<int,array<string,mixed>>
     */
    private function horarioPostComoFilas(array $dias): array
    {
        $filas = [];

        foreach ($dias as $dia => $d) {
            $filas[] = [
                'dia_semana'      => $dia,
                'hora_apertura'   => $d['apertura'],
                'hora_cierre'     => $d['cierre'],
                'hora_apertura_2' => $d['apertura2'],
                'hora_cierre_2'   => $d['cierre2'],
                'cerrado'         => $d['cerrado'] ? 1 : 0,
            ];
        }

        return $filas;
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->longitudMaxima('nombre_sitio', 'Nombre del sitio', 100)
            ->longitudMaxima('whatsapp_numero', 'WhatsApp', 20)
            ->longitudMaxima('boton_whatsapp_texto', 'Texto del botón de WhatsApp', 30)
            ->longitudMaxima('whatsapp_flotante_texto', 'Texto de la burbuja flotante de WhatsApp', 60)
            ->longitudMaxima('instagram_usuario', 'Instagram', 100)
            ->longitudMaxima('tiktok_usuario', 'TikTok', 100)
            ->longitudMaxima('facebook_nombre', 'Facebook', 100)
            ->longitudMaxima('frase_hero', 'Frase de bienvenida', 200)
            ->longitudMaxima('descripcion_corta', 'Descripción breve', 300)
            ->numerico('umbral_lealtad', 'Umbral de lealtad');

        $errores = $validador->errores();

        if ($datos['umbral_lealtad'] !== '' && (int) $datos['umbral_lealtad'] <= 0) {
            $errores[] = 'El umbral de lealtad debe ser un número mayor que cero.';
        }

        return $errores;
    }
}
