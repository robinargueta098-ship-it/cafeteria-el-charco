<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
use App\Core\Validator;
use App\Models\Categoria;
use App\Models\CuponPromocion;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\AuditoriaService;

class PromocionController extends Controller
{
    private const TIPOS_VALIDOS = ['descuento_porcentaje', 'descuento_monto', 'dos_por_uno', 'producto_gratis'];
    private const MAX_ETIQUETA = 40;

    /** Los tipos de siempre; "personalizada" (texto libre) solo cuando la base de datos ya lo soporta (migración 012). */
    private function tiposValidos(): array
    {
        return Promocion::soportaPersonalizada() ? array_merge(self::TIPOS_VALIDOS, ['personalizada']) : self::TIPOS_VALIDOS;
    }
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

        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/promociones');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $datos['imagen'] = $subida['nombre'];
            }
        }

        if ($errores !== []) {
            $this->formulario(array_merge($datos, ['id' => null]), $errores);
            return;
        }

        // Sin foto subida, la clave no existe todavía: el INSERT la necesita
        // (con PDO nativo un parámetro sin valor es "Invalid parameter number" → 500).
        $datos['imagen'] ??= null;

        $id = (new Promocion())->crear($datos);
        AuditoriaService::registrar('promociones', $id, 'crear', null, $datos);

        Flash::exito('Promoción creada correctamente.');
        $this->redirigir('/admin/promociones');
    }

    /** Sube o baja una posición en el orden en que se muestran en el sitio (flechas ▲▼ del listado). */
    public function mover(string $id): void
    {
        $this->requerirRol(1);

        $direccion = Entrada::texto($_POST, 'direccion', '');
        if (!in_array($direccion, ['subir', 'bajar'], true)) {
            Flash::error('Movimiento inválido.');
            $this->redirigir('/admin/promociones');
        }

        $resultado = (new Promocion())->mover((int) $id, $direccion);

        if ($resultado['resultado'] === 'no_existe') {
            $this->error404();
        }

        if ($resultado['resultado'] === 'movida') {
            AuditoriaService::registrar(
                'promociones', (int) $id, 'editar',
                ['posicion' => $resultado['desde']], ['posicion' => $resultado['hasta']]
            );
        }

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
        $errores = $this->validar($datos, (string) $existente['fecha_inicio']);

        $imagenNueva = null;
        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/promociones');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $imagenNueva = $subida['nombre'];
            }
        }

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->formulario($datos, $errores);
            return;
        }

        // Conserva la imagen anterior si no se subió una nueva.
        $datos['imagen'] = $imagenNueva ?? $existente['imagen'];

        $modelo->actualizar((int) $id, $datos);

        if ($imagenNueva !== null && $existente['imagen'] !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/promociones', $existente['imagen']);
        }

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

        // Si ya se reclamó algún cupón (§4.20), borrar la promoción dejaría ese
        // historial sin promoción a la que apuntar — igual que Temporadas con
        // sus productos asociados: se pide desactivarla en vez de borrarla.
        $cupones = (new CuponPromocion())->contarPorPromocion((int) $id);
        if ($cupones > 0) {
            Flash::error("No se puede eliminar: {$cupones} cupón(es) ya reclamados apuntan a esta promoción. Desactívala en vez de eliminarla.");
            $this->redirigir('/admin/promociones');
            return;
        }

        $modelo->eliminar((int) $id);

        if ($promocion['imagen'] !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/promociones', $promocion['imagen']);
        }

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
            'permitePersonalizada' => Promocion::soportaPersonalizada(),
        ]);
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        $alcance = Entrada::texto($_POST, 'alcance', 'todo');
        $categoriaId = $alcance === 'categoria' ? (int) ($_POST['categoria_id'] ?? 0) : null;
        $productoId = $alcance === 'producto' ? (int) ($_POST['producto_id'] ?? 0) : null;

        $tipo = Entrada::texto($_POST, 'tipo', '');
        $valor = trim(Entrada::texto($_POST, 'valor', ''));

        $dias = $_POST['dias_semana'] ?? [];
        $diasSemana = is_array($dias) && $dias !== []
            ? implode(',', array_map('intval', $dias))
            : null;

        return [
            'nombre'                 => trim(Entrada::texto($_POST, 'nombre', '')),
            'descripcion'            => trim(Entrada::texto($_POST, 'descripcion', '')) ?: null,
            'tipo'                   => $tipo,
            // Solo para el tipo "otra promoción": el texto que se muestra como etiqueta (ej. "3x1").
            'etiqueta_personalizada' => $tipo === 'personalizada' ? (trim(Entrada::texto($_POST, 'etiqueta_personalizada', '')) ?: null) : null,
            // El valor solo aplica a descuentos; en 2x1/producto gratis se ignora aunque venga en el POST.
            'valor'                  => in_array($tipo, self::TIPOS_CON_VALOR, true) && $valor !== '' ? $valor : null,
            'categoria_id'           => $categoriaId ?: null,
            'producto_id'            => $productoId ?: null,
            'fecha_inicio'           => Entrada::texto($_POST, 'fecha_inicio', ''),
            'fecha_fin'              => Entrada::texto($_POST, 'fecha_fin', ''),
            'dias_semana'            => $diasSemana,
            'hora_inicio'            => Entrada::texto($_POST, 'hora_inicio', '') ?: null,
            'hora_fin'               => Entrada::texto($_POST, 'hora_fin', '') ?: null,
            'combinable_con_lealtad' => isset($_POST['combinable_con_lealtad']) ? 1 : 0,
            'prioridad'              => (int) ($_POST['prioridad'] ?? 0),
            'activo'                 => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    /** @param string|null $inicioExistente fecha de inicio ya guardada (al editar): si no cambia, no se exige que sea futura */
    private function validar(array $datos, ?string $inicioExistente = null): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120)
            ->longitudMaxima('descripcion', 'Descripción', 255)
            ->requerido('fecha_inicio', 'Fecha de inicio')
            ->fecha('fecha_inicio', 'Fecha de inicio')
            ->requerido('fecha_fin', 'Fecha de fin')
            ->fecha('fecha_fin', 'Fecha de fin')
            ->enQueValores('tipo', 'Tipo', $this->tiposValidos());

        $errores = $validador->errores();

        if (!in_array($datos['tipo'], $this->tiposValidos(), true)) {
            $errores[] = 'Selecciona un tipo de promoción válido.';
        } elseif ($datos['tipo'] === 'personalizada') {
            if ($datos['etiqueta_personalizada'] === null) {
                $errores[] = 'Escribe el texto de la etiqueta de la promoción (ej. «3x1»).';
            } elseif (mb_strlen($datos['etiqueta_personalizada']) > self::MAX_ETIQUETA) {
                $errores[] = 'El texto de la etiqueta no puede pasar de ' . self::MAX_ETIQUETA . ' caracteres.';
            }
        } elseif (in_array($datos['tipo'], self::TIPOS_CON_VALOR, true)) {
            if ($datos['valor'] === null || !is_numeric($datos['valor']) || (float) $datos['valor'] <= 0) {
                $errores[] = 'Ingresa un valor numérico mayor que cero para este tipo de promoción.';
            } elseif ($datos['tipo'] === 'descuento_porcentaje' && (float) $datos['valor'] > 100) {
                $errores[] = 'El descuento porcentual no puede ser mayor a 100.';
            } else {
                // Mismo cuidado que con los precios: máximo 2 decimales, sin notación científica.
                $tope = $datos['tipo'] === 'descuento_porcentaje' ? 100.0 : Producto::PRECIO_MAXIMO;
                $v = new Validator(['valor' => $datos['valor']]);
                $v->precio('valor', 'El valor', $tope);
                $errores = array_merge($errores, $v->errores());
            }
        }

        if ($datos['prioridad'] < -100 || $datos['prioridad'] > 100) {
            $errores[] = 'La prioridad debe ser un número entre -100 y 100.';
        }

        $inicioValido = Validator::esFechaValida($datos['fecha_inicio']);
        $finValido = Validator::esFechaValida($datos['fecha_fin']);

        if ($inicioValido && $finValido && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $errores[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }

        // Una promoción nueva no puede empezar en el pasado. Al EDITAR una que ya empezó, la fecha de
        // inicio se deja como está (si no, no se podría ni corregir el nombre de una promoción vigente).
        if ($inicioValido && $datos['fecha_inicio'] < date('Y-m-d') && $datos['fecha_inicio'] !== $inicioExistente) {
            $errores[] = 'La fecha de inicio no puede ser anterior a hoy.';
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
