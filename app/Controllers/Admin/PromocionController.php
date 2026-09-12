<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\AuditoriaService;

class PromocionController extends Controller
{
    private const TIPOS_VALIDOS = ['descuento_porcentaje', 'descuento_monto', 'dos_por_uno', 'producto_gratis'];
    private const TIPOS_CON_VALOR = ['descuento_porcentaje', 'descuento_monto'];

    public function index(): void
    {
        $this->requerirRol(1);

        $this->vista('admin/promociones/index', [
            'seccionActiva' => 'promociones',
            'promociones'   => (new Promocion())->listarTodas(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1);
        $this->formulario(null, []);
    }

    public function crear(): void
    {
        $this->requerirRol(1);

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores !== []) {
            $this->formulario($datos, $errores);
            return;
        }

        $id = (new Promocion())->crear($datos);
        AuditoriaService::registrar('promociones', $id, 'crear', null, $datos);

        Flash::exito('Promoción creada correctamente.');
        $this->redirigir('/admin/promociones');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirRol(1);

        $promocion = (new Promocion())->buscarPorId((int) $id);
        if ($promocion === null) {
            $this->error404();
        }

        $this->formulario($promocion, []);
    }

    public function actualizar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Promocion();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->formulario($datos, $errores);
            return;
        }

        $modelo->actualizar((int) $id, $datos);
        AuditoriaService::registrar('promociones', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Promoción actualizada correctamente.');
        $this->redirigir('/admin/promociones');
    }

    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Promocion();
        $promocion = $modelo->buscarPorId((int) $id);
        if ($promocion === null) {
            $this->error404();
        }

        $modelo->eliminar((int) $id);
        AuditoriaService::registrar('promociones', (int) $id, 'eliminar', $promocion, null);

        Flash::exito('Promoción eliminada correctamente.');
        $this->redirigir('/admin/promociones');
    }

    private function formulario(?array $promocion, array $errores): void
    {
        $this->vista('admin/promociones/formulario', [
            'seccionActiva' => 'promociones',
            'promocion'     => $promocion,
            'errores'       => $errores,
            'categorias'    => (new Categoria())->listarActivas(),
            'productos'     => (new Producto())->listarActivos(),
        ]);
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        $alcance = (string) ($_POST['alcance'] ?? 'todo');
        $categoriaId = $alcance === 'categoria' ? (int) ($_POST['categoria_id'] ?? 0) : null;
        $productoId = $alcance === 'producto' ? (int) ($_POST['producto_id'] ?? 0) : null;

        $tipo = (string) ($_POST['tipo'] ?? '');
        $valor = trim((string) ($_POST['valor'] ?? ''));

        $dias = $_POST['dias_semana'] ?? [];
        $diasSemana = is_array($dias) && $dias !== []
            ? implode(',', array_map('intval', $dias))
            : null;

        return [
            'nombre'                 => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion'            => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'tipo'                   => $tipo,
            // El valor solo aplica a descuentos; en 2x1/producto gratis se ignora aunque venga en el POST.
            'valor'                  => in_array($tipo, self::TIPOS_CON_VALOR, true) && $valor !== '' ? $valor : null,
            'categoria_id'           => $categoriaId ?: null,
            'producto_id'            => $productoId ?: null,
            'fecha_inicio'           => (string) ($_POST['fecha_inicio'] ?? ''),
            'fecha_fin'              => (string) ($_POST['fecha_fin'] ?? ''),
            'dias_semana'            => $diasSemana,
            'hora_inicio'            => (string) ($_POST['hora_inicio'] ?? '') ?: null,
            'hora_fin'               => (string) ($_POST['hora_fin'] ?? '') ?: null,
            'combinable_con_lealtad' => isset($_POST['combinable_con_lealtad']) ? 1 : 0,
            'prioridad'              => (int) ($_POST['prioridad'] ?? 0),
            'activo'                 => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120)
            ->requerido('fecha_inicio', 'Fecha de inicio')
            ->requerido('fecha_fin', 'Fecha de fin')
            ->enQueValores('tipo', 'Tipo', self::TIPOS_VALIDOS);

        $errores = $validador->errores();

        if (!in_array($datos['tipo'], self::TIPOS_VALIDOS, true)) {
            $errores[] = 'Selecciona un tipo de promoción válido.';
        } elseif (in_array($datos['tipo'], self::TIPOS_CON_VALOR, true)) {
            if ($datos['valor'] === null || !is_numeric($datos['valor']) || (float) $datos['valor'] <= 0) {
                $errores[] = 'Ingresa un valor numérico mayor que cero para este tipo de promoción.';
            } elseif ($datos['tipo'] === 'descuento_porcentaje' && (float) $datos['valor'] > 100) {
                $errores[] = 'El descuento porcentual no puede ser mayor a 100.';
            }
        }

        if ($datos['fecha_inicio'] !== '' && $datos['fecha_fin'] !== '' && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        if ($datos['hora_inicio'] !== null && $datos['hora_fin'] !== null && $datos['hora_fin'] < $datos['hora_inicio']) {
            // Limitación conocida: no se soportan rangos que cruzan
            // medianoche (ej. 22:00 a 02:00). Ver docs/arquitectura.md.
            $errores[] = 'La hora de fin no puede ser anterior a la hora de inicio (no se soportan horarios que cruzan la medianoche).';
        }

        if ($datos['categoria_id'] !== null && (new Categoria())->buscarPorId($datos['categoria_id']) === null) {
            $errores[] = 'La categoría seleccionada no existe.';
        }

        if ($datos['producto_id'] !== null && (new Producto())->buscarPorId($datos['producto_id']) === null) {
            $errores[] = 'El producto seleccionado no existe.';
        }

        return $errores;
    }
}
