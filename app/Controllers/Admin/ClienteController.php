<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Entrada;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use PDOException;

class ClienteController extends Controller
{
    public function index(): void
    {
        $this->requerirRol(1, 2);

        $termino = trim(Entrada::texto($_GET, 'q', ''));

        $this->vista('admin/clientes/index', [
            'seccionActiva' => 'clientes',
            'clientes'      => (new Cliente())->buscar($termino),
            'termino'       => $termino,
        ]);
    }

    public function mostrarCrear(): void
    {
        $this->requerirRol(1, 2);
        $this->vista('admin/clientes/formulario', ['seccionActiva' => 'clientes', 'cliente' => null, 'errores' => []]);
    }

    public function crear(): void
    {
        $this->requerirRol(1, 2);

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores === []) {
            try {
                $id = (new Cliente())->crear($datos);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e; // no era un duplicado: no se disfraza de "ya existe"
                }
                $errores[] = 'Ya existe un cliente con ese número de teléfono.';
            }
        }

        if ($errores !== []) {
            $this->vista('admin/clientes/formulario', ['seccionActiva' => 'clientes', 'cliente' => $datos, 'errores' => $errores]);
            return;
        }

        AuditoriaService::registrar('clientes', $id, 'crear', null, $datos);

        Flash::exito('Cliente registrado correctamente.');
        $this->redirigir('/admin/clientes');
    }

    public function mostrarEditar(string $id): void
    {
        $this->requerirRol(1, 2);

        $cliente = (new Cliente())->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $this->vista('admin/clientes/formulario', ['seccionActiva' => 'clientes', 'cliente' => $cliente, 'errores' => []]);
    }

    public function actualizar(string $id): void
    {
        $this->requerirRol(1, 2);

        $modelo = new Cliente();
        $existente = $modelo->buscarPorId((int) $id);
        if ($existente === null) {
            $this->error404();
        }

        $datos = $this->datosDelFormulario();
        $errores = $this->validar($datos);

        if ($errores === []) {
            try {
                $modelo->actualizar((int) $id, $datos);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e; // no era un duplicado: no se disfraza de "ya existe"
                }
                $errores[] = 'Ya existe otro cliente con ese número de teléfono.';
            }
        }

        if ($errores !== []) {
            $datos['id'] = $id;
            $this->vista('admin/clientes/formulario', ['seccionActiva' => 'clientes', 'cliente' => $datos, 'errores' => $errores]);
            return;
        }

        AuditoriaService::registrar('clientes', (int) $id, 'editar', $existente, $datos);

        Flash::exito('Cliente actualizado correctamente.');
        $this->redirigir('/admin/clientes');
    }

    /** @return array<string,mixed> */
    private function datosDelFormulario(): array
    {
        return [
            'nombre'   => trim(Entrada::texto($_POST, 'nombre', '')),
            'telefono' => Cliente::normalizarTelefono(Entrada::texto($_POST, 'telefono', '')),
            'email'    => trim(Entrada::texto($_POST, 'email', '')) ?: null,
            'notas'    => trim(Entrada::texto($_POST, 'notas', '')) ?: null,
        ];
    }

    /** @return array<int,string> */
    private function validar(array $datos): array
    {
        $validador = new Validator($datos);
        $validador
            ->requerido('nombre', 'Nombre')
            ->longitudMaxima('nombre', 'Nombre', 120)
            ->requerido('telefono', 'Teléfono')
            ->longitudMaxima('telefono', 'Teléfono', 20)
            ->email('email', 'Correo')
            ->longitudMaxima('email', 'Correo', 150)
            ->longitudMaxima('notas', 'Notas', 255);

        $errores = $validador->errores();
        if ($datos['telefono'] !== '' && !Cliente::telefonoValido((string) $datos['telefono'])) {
            $errores[] = Cliente::MENSAJE_TELEFONO;
        }

        return $errores;
    }

    /**
     * Borra un cliente de prueba (o uno que lo pidió) junto con todo su historial de lealtad.
     * Solo Administrador: es irreversible, por eso queda registrado en Auditoría con lo que había.
     */
    public function eliminar(string $id): void
    {
        $this->requerirRol(1);

        $modelo = new Cliente();
        $cliente = $modelo->buscarPorId((int) $id);
        if ($cliente === null) {
            $this->error404();
        }

        $historial = $modelo->contarHistorial((int) $id);
        $modelo->eliminarConHistorial((int) $id);

        unset($cliente['pin_hash']); // nunca copiar el PIN (ni su hash) a la bitácora
        AuditoriaService::registrar('clientes', (int) $id, 'eliminar', $cliente + ['historial_borrado' => $historial], null);

        Flash::exito("Cliente «{$cliente['nombre']}» eliminado junto con su historial de lealtad.");
        $this->redirigir('/admin/clientes');
    }
}
