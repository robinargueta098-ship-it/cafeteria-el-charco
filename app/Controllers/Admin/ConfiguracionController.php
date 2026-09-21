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
        'whatsapp_numero', 'instagram_usuario', 'facebook_nombre', 'facebook_url',
        'umbral_lealtad', 'google_maps_embed', 'google_calificacion',
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
     */
    private function vistaFormulario(array $config, array $errores): void
    {
        $portada = new PortadaImagen();
        $cupo = PortadaController::MAX_IMAGENES - $portada->contar();

        $this->vista('admin/configuracion/formulario', [
            'seccionActiva'   => 'configuracion',
            'config'          => $config,
            'horarios'        => (new HorarioAtencion())->listarTodos(),
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

        $errores = $this->validar($datos);

        if ($errores !== []) {
            $this->vistaFormulario($datos + array_intersect_key($configActual, array_flip(self::CLAVES_ARCHIVO)), $errores);
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
            $this->vistaFormulario($configActual, $errores);
            return;
        }

        $configModelo->actualizarMultiples($datos);
        $this->guardarHorarios();

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

    private function guardarHorarios(): void
    {
        $modelo = new HorarioAtencion();

        for ($dia = 0; $dia <= 6; $dia++) {
            $cerrado = !isset($_POST['horario'][$dia]['abierto']);
            $apertura = trim((string) ($_POST['horario'][$dia]['apertura'] ?? '')) ?: null;
            $cierre = trim((string) ($_POST['horario'][$dia]['cierre'] ?? '')) ?: null;

            $modelo->actualizarDia($dia, $apertura, $cierre, $cerrado);
        }
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->longitudMaxima('nombre_sitio', 'Nombre del sitio', 100)
            ->longitudMaxima('whatsapp_numero', 'WhatsApp', 20)
            ->longitudMaxima('instagram_usuario', 'Instagram', 100)
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
