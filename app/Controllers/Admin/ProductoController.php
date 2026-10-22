<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\SubidaArchivo;
use App\Core\LechesProductos;
use App\Core\TemperaturaProductos;
use App\Core\Validator;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Producto;
use App\Models\Temporada;
use App\Services\AuditoriaService;

class ProductoController extends Controller
{
    public function index(): void
    {
        $this->requerirPermiso('productos', 'ver');

        $this->vista('admin/productos/index', [
            'seccionActiva' => 'productos',
            'productos'     => (new Producto())->listarTodos(),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirPermiso('productos', 'editar');
        $this->formulario(null, []);
    }

    public function crear(): void
    {
        $this->requerirPermiso('productos', 'editar');

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/productos');
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

        $modelo = new Producto();
        $id = $modelo->crear($datos);
        $modelo->sincronizarTemporadas($id, $this->temporadaIdsDelFormulario());
        TemperaturaProductos::guardar($id, $this->temperaturaDelFormulario());
        if ($this->lecheDelFormulario()) {
            LechesProductos::marcar($id, true);
        }

        AuditoriaService::registrar('productos', $id, 'crear', null, $datos + ['temperatura' => $this->temperaturaDelFormulario()]);

        Flash::exito('Producto creado correctamente.');
        $this->redirigir('/admin/productos');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirPermiso('productos', 'editar');

        $modelo = new Producto();
        $producto = $modelo->buscarPorId((int) $id);
        if ($producto === null) {
            $this->error404();
        }

        $producto['temporada_ids'] = $modelo->obtenerTemporadaIds((int) $id);
        $configProducto = (new Configuracion())->obtenerTodas();
        $producto['temperatura'] = TemperaturaProductos::mapa($configProducto)[(int) $id] ?? '';
        $producto['permite_leche'] = LechesProductos::permite($configProducto, (int) $id) ? 1 : 0;
        $this->formulario($producto, []);
    }

    public function actualizar(string $id): void
    {
        $this->requerirPermiso('productos', 'editar');

        $modelo = new Producto();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        $imagenNueva = null;
        if ($errores === [] && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $subida = SubidaArchivo::guardarImagen($_FILES['imagen'], UPLOADS_PATH . '/productos');
            if (!$subida['ok']) {
                $errores[] = $subida['error'];
            } else {
                $imagenNueva = $subida['nombre'];
            }
        }

        if ($errores !== []) {
            $datos['id'] = $id;
            $datos['temporada_ids'] = $this->temporadaIdsDelFormulario();
            $datos['temperatura'] = $this->temperaturaDelFormulario();
            $datos['permite_leche'] = $this->lecheDelFormulario() ? 1 : 0;
            $this->formulario($datos, $errores);
            return;
        }

        // Conserva la imagen anterior si no se subió una nueva.
        $datos['imagen'] = $imagenNueva ?? $existente['imagen'];

        $modelo->actualizar((int) $id, $datos);
        $modelo->sincronizarTemporadas((int) $id, $this->temporadaIdsDelFormulario());
        TemperaturaProductos::guardar((int) $id, $this->temperaturaDelFormulario());
        LechesProductos::marcar((int) $id, $this->lecheDelFormulario());

        if ($imagenNueva !== null && $existente['imagen'] !== null) {
            SubidaArchivo::eliminar(UPLOADS_PATH . '/productos', $existente['imagen']);
        }

        AuditoriaService::registrar('productos', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Producto actualizado correctamente.');
        $this->redirigir('/admin/productos');
    }

    /** "Eliminar" un producto en realidad alterna su estado activo/inactivo (nunca borrado físico, ver docs/arquitectura.md §8). */
    public function alternarEstado(string $id): void
    {
        $this->requerirPermiso('productos', 'editar');

        $modelo = new Producto();
        $producto = $modelo->buscarPorId((int) $id);
        if ($producto === null) {
            $this->error404();
        }

        $nuevoEstado = (int) $producto['activo'] === 1 ? 0 : 1;
        $modelo->alternarActivo((int) $id, $nuevoEstado === 1);

        AuditoriaService::registrar('productos', (int) $id, 'editar', ['activo' => $producto['activo']], ['activo' => $nuevoEstado]);

        Flash::exito($nuevoEstado === 1 ? 'Producto activado.' : 'Producto desactivado.');
        $this->redirigir('/admin/productos');
    }

    private function formulario(?array $producto, array $errores): void
    {
        $this->vista('admin/productos/formulario', [
            'seccionActiva' => 'productos',
            'producto'      => $producto,
            'errores'       => $errores,
            'categorias'    => (new Categoria())->listarActivas(),
            'temporadas'    => (new Temporada())->listarTodas(),
        ]);
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        $precioAlt = trim(Entrada::texto($_POST, 'precio_alt', ''));
        $etiquetaAlt = trim(Entrada::texto($_POST, 'etiqueta_precio_alt', ''));

        return [
            'categoria_id'        => (int) ($_POST['categoria_id'] ?? 0),
            'nombre'              => trim(Entrada::texto($_POST, 'nombre', '')),
            'descripcion'         => $this->nuloSiVacio(Entrada::texto($_POST, 'descripcion')),
            'precio'              => Entrada::texto($_POST, 'precio', ''),
            'precio_alt'          => $precioAlt === '' ? null : $precioAlt,
            'etiqueta_precio_alt' => $etiquetaAlt === '' ? null : $etiquetaAlt,
            'es_destacado'        => isset($_POST['es_destacado']) ? 1 : 0,
            'es_mas_vendido'      => isset($_POST['es_mas_vendido']) ? 1 : 0,
            'activo'              => isset($_POST['activo']) ? 1 : 0,
            'orden'               => (int) ($_POST['orden'] ?? 0),
        ];
    }

    /** ¿Se marcó «Permite elegir leche»? Va aparte de $datos: se guarda en la configuración, no en `productos`. */
    private function lecheDelFormulario(): bool
    {
        return isset($_POST['permite_leche']);
    }

    /** 'ambas' | 'caliente' | 'frio' | '' (sin opción de temperatura). Va aparte de $datos: no es una columna de `productos`. */
    private function temperaturaDelFormulario(): string
    {
        $valor = Entrada::texto($_POST, 'temperatura', '');

        return in_array($valor, TemperaturaProductos::VALIDAS, true) ? $valor : '';
    }

    /** @return array<int,int> */
    private function temporadaIdsDelFormulario(): array
    {
        $valores = $_POST['temporadas'] ?? [];
        if (!is_array($valores)) {
            return [];
        }

        return array_map('intval', $valores);
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120)
            ->longitudMaxima('descripcion', 'Descripción', 255)
            ->longitudMaxima('etiqueta_precio_alt', 'La etiqueta del precio alternativo', 40)
            ->requerido('precio', 'Precio')
            ->precio('precio', 'Precio', Producto::PRECIO_MAXIMO);

        $errores = $validador->errores();

        if (is_numeric($datos['precio']) && (float) $datos['precio'] <= 0) {
            $errores[] = 'El precio debe ser mayor que cero.';
        }

        if ($datos['precio_alt'] !== null) {
            $alt = new Validator(['precio_alt' => $datos['precio_alt']]);
            $alt->precio('precio_alt', 'Precio alternativo', Producto::PRECIO_MAXIMO);
            $errores = array_merge($errores, $alt->errores());
            if ($alt->esValido() && (float) $datos['precio_alt'] <= 0) {
                $errores[] = 'El precio alternativo debe ser mayor que cero.';
            }
            if ($datos['etiqueta_precio_alt'] === null) {
                $errores[] = 'Si defines un precio alternativo, escribe también su etiqueta (ej. "Sin café").';
            }
        }

        if ($datos['orden'] < 0 || $datos['orden'] > 9999) {
            $errores[] = 'El orden debe ser un número entre 0 y 9999.';
        }

        if ($datos['categoria_id'] <= 0 || !(new Categoria())->buscarPorId($datos['categoria_id'])) {
            $errores[] = 'Selecciona una categoría válida.';
        }

        return $errores;
    }

    private function nuloSiVacio($valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }
}
