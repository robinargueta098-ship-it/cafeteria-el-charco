<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

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

        $termino = trim((string) ($_GET['q'] ?? ''));

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
            'nombre'   => trim((string) ($_POST['nombre'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')) ?: null,
            'notas'    => trim((string) ($_POST['notas'] ?? '')) ?: null,
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
            ->email('email', 'Correo');

        return $validador->errores();
    }
}
