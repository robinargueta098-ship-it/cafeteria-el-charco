<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Dinero;
use App\Core\Entrada;
use App\Core\Flash;
use App\Core\LechesProductos;
use App\Core\OpcionesProducto;
use App\Core\Validator;
use App\Models\Configuracion;
use App\Models\Leche;
use App\Models\Producto;
use App\Services\AuditoriaService;

/**
 * «Leches»: las leches que el cliente puede elegir en el carrito (nombre, lo que suman al precio y si están activas),
 * y qué productos permiten elegirla. El precio de cada leche se cambia aquí y se refleja de inmediato en el sitio,
 * el carrito y los pedidos nuevos (los pedidos ya hechos conservan el precio con que se guardaron).
 */
class LecheController extends Controller
{
    public function index(): void
    {
        $this->requerirPermiso('leches', 'ver');

        $productos = (new Producto())->listarTodos();
        $porCategoria = [];
        foreach ($productos as $p) {
            $porCategoria[(string) ($p['categoria_nombre'] ?? 'Sin categoría')][] = $p;
        }

        $this->vista('admin/leches/index', [
            'seccionActiva' => 'leches',
            'leches'        => (new Leche())->listarTodas(),
            'porCategoria'  => $porCategoria,
            'conLeche'      => LechesProductos::mapa((new Configuracion())->obtenerTodas()),
            'listasOpcion'  => OpcionesProducto::listas((new Configuracion())->obtenerTodas()),
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirPermiso('leches', 'editar');

        $this->vista('admin/leches/formulario', ['seccionActiva' => 'leches', 'leche' => null, 'errores' => []]);
    }

    public function crear(): void
    {
        $this->requerirPermiso('leches', 'editar');

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);
        if ($errores !== []) {
            $this->vista('admin/leches/formulario', ['seccionActiva' => 'leches', 'leche' => $datos, 'errores' => $errores]);
            return;
        }

        $id = (new Leche())->crear($datos);
        AuditoriaService::registrar('leches', $id, 'crear', null, $datos);

        Flash::exito('Leche agregada. Ya se puede elegir en los productos que la permiten.');
        $this->redirigir('/admin/leches');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirPermiso('leches', 'editar');

        $leche = (new Leche())->buscarPorId((int) $id);
        if ($leche === null) {
            $this->error404();
            return;
        }

        $this->vista('admin/leches/formulario', ['seccionActiva' => 'leches', 'leche' => $leche, 'errores' => []]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirPermiso('leches', 'editar');

        $modelo = new Leche();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
            return;
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos, (int) $id);
        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/leches/formulario', ['seccionActiva' => 'leches', 'leche' => $datos, 'errores' => $errores]);
            return;
        }

        $modelo->actualizar((int) $id, $datos);
        AuditoriaService::registrar('leches', (int) $id, 'editar', $existente, $datos + ['nombre' => $datos['nombre']]);

        Flash::exito('Leche actualizada. El cambio ya se ve en el sitio.');
        $this->redirigir('/admin/leches');
    }

    /** Activa o desactiva (una leche desactivada deja de ofrecerse, sin borrarla). */
    public function alternarEstado(string $id): void
    {
        $this->requerirPermiso('leches', 'editar');

        $modelo = new Leche();
        $leche = $modelo->buscarPorId((int) $id);
        if ($leche === null) {
            $this->error404();
            return;
        }

        $nuevo = (int) $leche['activo'] === 1 ? 0 : 1;
        $modelo->actualizar((int) $id, ['nombre' => $leche['nombre'], 'precio_extra' => $leche['precio_extra'], 'activo' => $nuevo, 'solo_productos' => (int) $leche['solo_productos']]);
        AuditoriaService::registrar('leches', (int) $id, 'editar', ['activo' => (int) $leche['activo'], 'nombre' => $leche['nombre']], ['activo' => $nuevo, 'nombre' => $leche['nombre']]);

        Flash::exito($nuevo === 1 ? 'Leche activada.' : 'Leche desactivada: ya no se ofrece en el sitio.');
        $this->redirigir('/admin/leches');
    }

    public function mover(string $id): void
    {
        $this->requerirPermiso('leches', 'editar');

        $direccion = Entrada::texto($_POST, 'direccion', '') === 'bajar' ? 'bajar' : 'subir';
        $resultado = (new Leche())->mover((int) $id, $direccion);
        if ($resultado === 'no_existe') {
            $this->error404();
            return;
        }

        $this->redirigir('/admin/leches');
    }

    public function eliminar(string $id): void
    {
        $this->requerirPermiso('leches', 'editar');

        $modelo = new Leche();
        $leche = $modelo->buscarPorId((int) $id);
        if ($leche === null) {
            $this->error404();
            return;
        }

        $modelo->eliminar((int) $id);
        AuditoriaService::registrar('leches', (int) $id, 'eliminar', $leche, null);

        Flash::exito('Leche eliminada. Los pedidos ya hechos conservan su nombre y su precio.');
        $this->redirigir('/admin/leches');
    }

    /**
     * Guarda de una vez qué se ofrece en cada producto: la columna «Leche» (todas las leches normales) y una columna por
     * cada opción restringida (p. ej. Agua). Campos: producto[id] y opcion[id de la opción][id del producto].
     */
    public function guardarProductos(): void
    {
        $this->requerirPermiso('leches', 'editar');

        $config = (new Configuracion())->obtenerTodas();
        $antes = count(LechesProductos::mapa($config));

        $marcados = isset($_POST['producto']) && is_array($_POST['producto']) ? array_keys($_POST['producto']) : [];
        LechesProductos::reemplazar($marcados);

        $resumen = [];
        foreach ((new Leche())->listarTodas() as $opcion) {
            if ((int) $opcion['solo_productos'] !== 1) {
                continue;
            }
            $ids = isset($_POST['opcion'][$opcion['id']]) && is_array($_POST['opcion'][$opcion['id']]) ? array_keys($_POST['opcion'][$opcion['id']]) : [];
            OpcionesProducto::guardarLista((int) $opcion['id'], $ids);
            $resumen[] = $opcion['nombre'] . ': ' . count(OpcionesProducto::listas((new Configuracion())->obtenerTodas())[(int) $opcion['id']] ?? []);
        }

        $despues = count(LechesProductos::mapa((new Configuracion())->obtenerTodas()));
        AuditoriaService::registrar('leches', 0, 'editar', null, [
            'detalle' => 'Cambió en qué productos se ofrece «Preparar con» (leches: ' . $antes . ' → ' . $despues . ' productos'
                . ($resumen !== [] ? '; ' . implode('; ', $resumen) : '') . ')',
        ]);

        Flash::exito('Listo: se guardó en qué productos se ofrece cada opción.');
        $this->redirigir('/admin/leches');
    }

    /** @return array{nombre:string,precio_extra:string,activo:int,solo_productos:int} */
    private function datosDelFormulario(): array
    {
        $precio = trim(Entrada::texto($_POST, 'precio_extra', ''));

        return [
            'nombre'       => trim(Entrada::texto($_POST, 'nombre', '')),
            'precio_extra' => $precio === '' ? '0' : $precio,
            'activo'       => isset($_POST['activo']) ? 1 : 0,
            'solo_productos' => isset($_POST['solo_productos']) ? 1 : 0,
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos, ?int $excluirId = null): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 40)
            ->precio('precio_extra', 'El precio extra', Leche::PRECIO_MAXIMO);
        $errores = $validador->errores();

        if ($datos['nombre'] !== '' && (new Leche())->existeNombre($datos['nombre'], $excluirId)) {
            $errores[] = 'Ya existe una leche con ese nombre.';
        }

        return $errores;
    }
}
