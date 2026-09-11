<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validador mínimo reutilizable para datos de formularios (Fases 4-6).
 * Se usa encadenado: cada regla agrega un error solo si falla, y el
 * controlador decide qué hacer con esValido()/errores().
 *
 * Ejemplo:
 *   $v = new Validator($_POST);
 *   $v->requerido('nombre', 'Nombre')->longitudMaxima('nombre', 'Nombre', 120);
 *   if (!$v->esValido()) { ... }
 */
class Validator
{
    /** @var array<string,mixed> */
    private array $datos;

    /** @var array<int,string> */
    private array $errores = [];

    /** @param array<string,mixed> $datos */
    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function requerido(string $campo, string $etiqueta): static
    {
        $valor = $this->valor($campo);

        if ($valor === null || $valor === '') {
            $this->errores[] = "{$etiqueta} es obligatorio.";
        }

        return $this;
    }

    public function longitudMaxima(string $campo, string $etiqueta, int $maximo): static
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && mb_strlen($valor) > $maximo) {
            $this->errores[] = "{$etiqueta} no puede tener más de {$maximo} caracteres.";
        }

        return $this;
    }

    public function longitudMinima(string $campo, string $etiqueta, int $minimo): static
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && mb_strlen($valor) < $minimo) {
            $this->errores[] = "{$etiqueta} debe tener al menos {$minimo} caracteres.";
        }

        return $this;
    }

    public function numerico(string $campo, string $etiqueta): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !is_numeric($valor)) {
            $this->errores[] = "{$etiqueta} debe ser un número.";
        }

        return $this;
    }

    public function email(string $campo, string $etiqueta): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->errores[] = "{$etiqueta} no es un correo válido.";
        }

        return $this;
    }

    /** @param array<int,mixed> $valoresPermitidos */
    public function enQueValores(string $campo, string $etiqueta, array $valoresPermitidos): static
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !in_array($valor, $valoresPermitidos, true)) {
            $this->errores[] = "{$etiqueta} no es válido.";
        }

        return $this;
    }

    public function esValido(): bool
    {
        return $this->errores === [];
    }

    /** @return array<int,string> */
    public function errores(): array
    {
        return $this->errores;
    }

    public function primerError(): ?string
    {
        return $this->errores[0] ?? null;
    }

    private function valor(string $campo): mixed
    {
        return $this->datos[$campo] ?? null;
    }
}
