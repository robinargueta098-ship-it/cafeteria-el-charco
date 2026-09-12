<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Configuracion;
use App\Models\HorarioAtencion;
use App\Services\AuditoriaService;

class ConfiguracionController extends Controller
{
    /** Claves editables desde este formulario (evita que alguien inyecte una clave nueva arbitraria). */
    private const CLAVES_EDITABLES = [
        'nombre_sitio', 'logo', 'direccion_texto', 'frase_hero', 'descripcion_corta', 'hero_imagen',
        'whatsapp_numero', 'instagram_usuario', 'facebook_nombre', 'facebook_url',
        'umbral_lealtad', 'google_maps_embed', 'google_calificacion',
        'google_calificacion_url', 'sobre_nosotros_texto',
    ];

    /** Estas dos se suben como archivo, no se escriben a mano — se manejan aparte del resto. */
    private const CLAVES_ARCHIVO = ['logo', 'hero_imagen'];

    public function mostrar(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/configuracion/formulario', [
            'seccionActiva' => 'configuracion',
            'config'        => (new Configuracion())->obtenerTodas(),
            'horarios'      => (new HorarioAtencion())->listarTodos(),
            'errores'       => [],
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
            $this->vista('admin/configuracion/formulario', [
                'seccionActiva' => 'configuracion',
                'config'        => $datos + array_intersect_key($configActual, array_flip(self::CLAVES_ARCHIVO)),
                'horarios'      => (new HorarioAtencion())->listarTodos(),
                'errores'       => $errores,
            ]);
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
            $this->vista('admin/configuracion/formulario', [
                'seccionActiva' => 'configuracion',
                'config'        => $configActual,
                'horarios'      => (new HorarioAtencion())->listarTodos(),
                'errores'       => $errores,
            ]);
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
