<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Entrada;
use App\Core\Flash;
use App\Core\GoogleMaps;
use App\Core\ImagenOptimizada;
use App\Core\SobreNosotros;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Configuracion;
use App\Models\Galeria;
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
        'google_calificacion_url', 'carrito_aviso_ubicacion', 'sobre_nosotros_foto_pie',
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
    private function vistaFormulario(array $config, array $errores, ?array $horarios = null, ?array $sobre = null): void
    {
        $portada = new PortadaImagen();
        $cupo = PortadaController::MAX_IMAGENES - $portada->contar();

        $this->vista('admin/configuracion/formulario', [
            'seccionActiva'   => 'configuracion',
            'config'          => $config,
            'sobre'           => $sobre ?? SobreNosotros::estructura($config),
            'fotoGaleria'     => SobreNosotros::foto(['sobre_nosotros_foto' => ''], (new Galeria())->listarActivas(), (string) ($config['nombre_sitio'] ?? '')),
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

        // SOLO se actualiza lo que realmente llega en la petición. Un ajuste que no viene en el envío
        // (formulario incompleto, petición manipulada, pestaña vieja…) se deja exactamente como estaba:
        // antes se tomaba como "vacío" y se borraba (nombre, WhatsApp, mapa, redes, horarios…).
        $datos = [];
        $errores = [];
        $omitidos = [];
        foreach (self::CLAVES_EDITABLES as $clave) {
            if (in_array($clave, self::CLAVES_ARCHIVO, true)) {
                continue; // se resuelven abajo: o hay archivo nuevo, o se conserva el actual
            }
            if (!array_key_exists($clave, $_POST)) {
                $omitidos[] = $clave;
                continue;
            }
            if (!is_string($_POST[$clave])) {
                $errores[] = "El campo «{$clave}» no tiene un formato válido; no se guardó nada.";
                continue;
            }
            $datos[$clave] = trim($_POST[$clave]);
        }

        $horarioPost = $this->leerHorarioDesdePost();   // solo los días que vienen en el envío

        // "Sobre nosotros": introducción, subtítulo, tarjetas y cierre. Solo se toca si el envío trae esos campos.
        $sobre = null;
        if (SobreNosotros::hayEnvio($_POST)) {
            $resultadoSobre = SobreNosotros::desdeEnvio($_POST, SobreNosotros::estructura($configActual));
            $sobre = $resultadoSobre['estructura'];
            $errores = array_merge($errores, $resultadoSobre['errores']);
        }

        $subeLogo = ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $subeFotoNosotros = ($_FILES[SobreNosotros::CLAVE_FOTO]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $quitaFotoNosotros = isset($_POST['quitar_sobre_nosotros_foto']);
        if ($datos === [] && $horarioPost === [] && !$subeLogo && $sobre === null && !$subeFotoNosotros && !$quitaFotoNosotros && $errores === []) {
            $errores[] = 'El envío no traía ningún dato de configuración; no se cambió nada.';
        }

        $errores = array_merge($errores, $this->validar($datos), $this->validarHorario($horarioPost));

        if ($errores !== []) {
            if ($subeFotoNosotros || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $errores[] = 'Los archivos no se pueden conservar al corregir: vuelve a elegir la foto (o el logo) antes de guardar.';
            }
            $this->vistaFormulario(
                array_merge($configActual, $datos),
                $errores,
                $this->horarioPostComoFilas($horarioPost),
                $sobre
            );
            return;
        }

        // Foto propia de "Sobre nosotros" (si falla, nada se guarda y lo escrito se vuelve a mostrar).
        $fotoActual = (string) ($configActual[SobreNosotros::CLAVE_FOTO] ?? '');
        $accionFoto = null;          // 'subida' | 'reemplazada' | 'quitada'
        $fotoNuevaGuardada = '';
        if ($subeFotoNosotros) {
            $subidaFoto = SubidaArchivo::guardarImagen($_FILES[SobreNosotros::CLAVE_FOTO], UPLOADS_PATH . '/sitio');
            if (!$subidaFoto['ok']) {
                $this->vistaFormulario(
                    array_merge($configActual, $datos),
                    ['Foto de «Sobre nosotros»: ' . $subidaFoto['error'] . ' No se guardó ningún cambio: vuelve a elegir la foto.'],
                    $this->horarioPostComoFilas($horarioPost),
                    $sobre
                );
                return;
            }
            $fotoNuevaGuardada = $subidaFoto['nombre'];
            $datos[SobreNosotros::CLAVE_FOTO] = $fotoNuevaGuardada;
            $accionFoto = $fotoActual !== '' ? 'reemplazada' : 'subida';
        } elseif ($quitaFotoNosotros && $fotoActual !== '') {
            $datos[SobreNosotros::CLAVE_FOTO] = '';
            $accionFoto = 'quitada';
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
            SubidaArchivo::eliminar(UPLOADS_PATH . '/sitio', $fotoNuevaGuardada);   // no dejar la foto nueva huérfana si no se guarda nada
            $this->vistaFormulario(array_merge($configActual, $datos, [SobreNosotros::CLAVE_FOTO => $fotoActual]), $errores, $this->horarioPostComoFilas($horarioPost), $sobre);
            return;
        }

        // El mapa se centra donde apunta el enlace de Google Maps: al pegar un enlace nuevo, las
        // coordenadas se sacan solas de él (salvo que el Administrador haya escrito otras a mano
        // en esta misma edición). Si no se pudieran leer, se conservan las que ya había.
        $avisoMapa = null;
        $enlaceCambio = ($datos['google_maps_embed'] ?? '') !== '' && $datos['google_maps_embed'] !== ($configActual['google_maps_embed'] ?? '');
        $coordenadasManuales = isset($datos['mapa_coordenadas']) && $datos['mapa_coordenadas'] !== ($configActual['mapa_coordenadas'] ?? '');
        if ($coordenadasManuales) {
            // Coordenadas escritas a mano: el mapa vuelve a ser un pin en ese punto, sin ficha del lugar.
            $datos['mapa_lugar_id'] = '';
            $datos['mapa_lugar_nombre'] = '';
        }
        if ($enlaceCambio && !$coordenadasManuales) {
            $lugar = GoogleMaps::datosDesdeEnlace($datos['google_maps_embed']);
            if ($lugar !== null) {
                $datos['mapa_coordenadas'] = $lugar['coordenadas'];
                $datos['mapa_lugar_id'] = $lugar['lugar_id'];
                $datos['mapa_lugar_nombre'] = $lugar['nombre'];
            } else {
                $avisoMapa = 'No se pudieron leer las coordenadas del enlace de Google Maps; el mapa conserva la ubicación anterior. Puedes escribirlas a mano en "Coordenadas exactas".';
            }
        }

        if (array_key_exists(Configuracion::CLAVE_AVISO_UBICACION, $datos)) {
            // Vacío = vuelve al texto de siempre; y si la fila aún no existe (instalación sin la migración 007) se crea.
            if ($datos[Configuracion::CLAVE_AVISO_UBICACION] === '') {
                $datos[Configuracion::CLAVE_AVISO_UBICACION] = Configuracion::AVISO_UBICACION_DEFECTO;
            }
            $configModelo->asegurar(Configuracion::CLAVE_AVISO_UBICACION, Configuracion::AVISO_UBICACION_DEFECTO, 'Aviso del carrito: pedir la ubicación por WhatsApp después de enviar el pedido');
        }

        $cambioSobre = false;
        if ($sobre !== null) {
            $datos[SobreNosotros::CLAVE_ESTRUCTURA] = SobreNosotros::aJson($sobre);
            $cambioSobre = $datos[SobreNosotros::CLAVE_ESTRUCTURA] !== ($configActual[SobreNosotros::CLAVE_ESTRUCTURA] ?? '');
        }
        // Las claves de "Sobre nosotros" se crean si todavía no existen (instalación sin la migración 008).
        foreach ([SobreNosotros::CLAVE_ESTRUCTURA => 'Sobre nosotros: estructura (introducción, subtítulo, tarjetas y cierre) en JSON',
                  SobreNosotros::CLAVE_FOTO => 'Sobre nosotros: foto propia (archivo en uploads/sitio)',
                  SobreNosotros::CLAVE_FOTO_PIE => 'Sobre nosotros: pie de foto (texto visible debajo de la foto)'] as $claveNueva => $descripcionNueva) {
            if (array_key_exists($claveNueva, $datos)) {
                $configModelo->asegurar($claveNueva, '', $descripcionNueva);
            }
        }

        $configModelo->actualizarMultiples($datos);
        $this->guardarHorario($horarioPost);

        // La foto anterior se borra del disco, salvo que otro ajuste todavía apunte al mismo archivo.
        if ($fotoActual !== '' && $accionFoto !== null) {
            $otrosValores = array_values(array_diff_key(array_merge($configActual, $datos), [SobreNosotros::CLAVE_FOTO => 1]));
            if (!in_array($fotoActual, $otrosValores, true)) {
                SubidaArchivo::eliminar(UPLOADS_PATH . '/sitio', $fotoActual);
            }
        }

        AuditoriaService::registrar('configuracion_sistema', 0, 'editar', $configActual, $datos);
        if ($cambioSobre) {
            AuditoriaService::registrar('configuracion_sistema', 0, 'editar', null, ['detalle' => 'Texto de «Sobre nosotros» editado (introducción, tarjetas o frase de cierre)']);
        }
        if ($accionFoto !== null) {
            AuditoriaService::registrar('configuracion_sistema', 0, 'editar', null, ['detalle' => 'Foto de «Sobre nosotros»: ' . $accionFoto]);
        }

        if ($avisoMapa !== null) {
            Flash::error('Configuración guardada, pero: ' . $avisoMapa);
            $this->redirigir('/admin/configuracion');
        }

        $aviso = '';
        if ($omitidos !== []) {
            $aviso = ' Estos ajustes no venían en el envío y se dejaron como estaban: ' . implode(', ', array_slice($omitidos, 0, 6)) . (count($omitidos) > 6 ? '…' : '') . '.';
        }

        Flash::exito('Configuración actualizada correctamente.' . $aviso);
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
     * Solo incluye los días que vienen en la petición; los demás se dejan como están. (Un día enviado sin la
     * casilla "abierto" cuenta como cerrado: así es como el formulario real marca un día cerrado.)
     *
     * @return array<int,array{cerrado:bool,apertura:?string,cierre:?string,apertura2:?string,cierre2:?string}>
     */
    private function leerHorarioDesdePost(): array
    {
        $dias = [];
        // Si 'horario' llega como texto o con otra forma (petición manipulada), se trata como vacío.
        $horarioPost = is_array($_POST['horario'] ?? null) ? $_POST['horario'] : [];

        for ($dia = 0; $dia <= 6; $dia++) {
            if (!is_array($horarioPost[$dia] ?? null)) {
                continue; // este día no vino en el envío: su horario guardado no se toca
            }

            // substr(...,0,5): defensa en el servidor, además del recorte ya
            // hecho al imprimir el value= del <input type="time"> (formulario.php)
            // — un navegador que reciba un value con segundos (como venían los
            // horarios ya guardados) los reenvía tal cual al guardar, y eso
            // no pasaba la validación de formato "HH:MM" más abajo, bloqueando
            // el guardado de TODO el formulario, no solo del horario.
            $campo = function (string $nombre) use ($horarioPost, $dia): ?string {
                $valor = is_array($horarioPost[$dia] ?? null) ? ($horarioPost[$dia][$nombre] ?? '') : '';

                return substr(trim(is_string($valor) ? $valor : ''), 0, 5) ?: null;
            };

            $dias[$dia] = [
                'cerrado'   => !(is_array($horarioPost[$dia] ?? null) && isset($horarioPost[$dia]['abierto'])),
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
        // Base: lo guardado. Encima, los días que vinieron en el envío (con lo que el usuario acababa de escribir).
        $filas = [];
        foreach ((new HorarioAtencion())->listarTodos() as $fila) {
            $filas[(int) $fila['dia_semana']] = $fila;
        }

        foreach ($dias as $dia => $d) {
            $filas[$dia] = [
                'dia_semana'      => $dia,
                'hora_apertura'   => $d['apertura'],
                'hora_cierre'     => $d['cierre'],
                'hora_apertura_2' => $d['apertura2'],
                'hora_cierre_2'   => $d['cierre2'],
                'cerrado'         => $d['cerrado'] ? 1 : 0,
            ];
        }

        ksort($filas);

        return array_values($filas);
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
            ->longitudMaxima('google_maps_embed', 'Enlace de Google Maps', 255)
            ->longitudMaxima('carrito_aviso_ubicacion', 'Aviso de ubicación del carrito', 300)
            ->longitudMaxima('sobre_nosotros_foto_pie', 'Pie de foto de «Sobre nosotros»', SobreNosotros::MAX_PIE);

        $errores = $validador->errores();

        // Obligatorios: si vienen en el envío no pueden ir vacíos (un sitio sin nombre o sin umbral no funciona).
        if (array_key_exists('nombre_sitio', $datos) && $datos['nombre_sitio'] === '') {
            $errores[] = 'El nombre del sitio es obligatorio.';
        }
        if (array_key_exists('umbral_lealtad', $datos) && $datos['umbral_lealtad'] === '') {
            $errores[] = 'El umbral de lealtad es obligatorio (cuántos cafés dan 1 gratis).';
        }

        // Umbral de lealtad: entero entre 1 y 100 (sin decimales ni notación científica).
        $umbral = $datos['umbral_lealtad'] ?? '';
        if ($umbral !== '' && preg_match('/^\d{1,3}$/', $umbral) !== 1) {
            $errores[] = 'El umbral de lealtad debe ser un número entero (ej. 10), sin decimales.';
        } elseif ($umbral !== '' && ((int) $umbral < 1 || (int) $umbral > 100)) {
            $errores[] = 'El umbral de lealtad debe estar entre 1 y 100 cafés.';
        }

        $coordenadas = trim((string) ($datos['mapa_coordenadas'] ?? ''));
        if ($coordenadas !== '') {
            if (preg_match('/^(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $coordenadas, $m) !== 1
                || abs((float) $m[1]) > 90 || abs((float) $m[2]) > 180) {
                $errores[] = 'Las coordenadas del mapa deben tener el formato "latitud, longitud" (ej. 15.0176, -91.1525).';
            }
        }

        $enlaceMapa = (string) ($datos['google_maps_embed'] ?? '');
        if ($enlaceMapa !== '' && !preg_match('#^https://#i', $enlaceMapa)) {
            $errores[] = 'El enlace de Google Maps debe empezar con https://';
        }

        return $errores;
    }
}
