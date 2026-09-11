<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Configuracion;
use App\Models\HorarioAtencion;
use App\Services\AuditoriaService;

class ConfiguracionController extends Controller
{
    /** Claves editables desde este formulario (evita que alguien inyecte una clave nueva arbitraria). */
    private const CLAVES_EDITABLES = [
        'nombre_sitio', 'logo', 'direccion_texto',
        'whatsapp_numero', 'instagram_usuario', 'facebook_nombre',
        'umbral_lealtad', 'google_maps_embed', 'google_calificacion',
        'google_calificacion_url', 'sobre_nosotros_texto',
    ];

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

        $datos = [];
        foreach (self::CLAVES_EDITABLES as $clave) {
            $datos[$clave] = trim((string) ($_POST[$clave] ?? ''));
        }

        $errores = $this->validar($datos);

        if ($errores !== []) {
            $this->vista('admin/configuracion/formulario', [
                'seccionActiva' => 'configuracion',
                'config'        => $datos,
                'horarios'      => (new HorarioAtencion())->listarTodos(),
                'errores'       => $errores,
            ]);
            return;
        }

        $antes = (new Configuracion())->obtenerTodas();
        (new Configuracion())->actualizarMultiples($datos);
        $this->guardarHorarios();

        AuditoriaService::registrar('configuracion_sistema', 0, 'editar', $antes, $datos);

        Flash::exito('Configuración actualizada correctamente.');
        $this->redirigir('/admin/configuracion');
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
            ->numerico('umbral_lealtad', 'Umbral de lealtad');

        $errores = $validador->errores();

        if ($datos['umbral_lealtad'] !== '' && (int) $datos['umbral_lealtad'] <= 0) {
            $errores[] = 'El umbral de lealtad debe ser un número mayor que cero.';
        }

        return $errores;
    }
}
