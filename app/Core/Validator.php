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

    public function requerido(string $campo, string $etiqueta)
    {
        $valor = $this->valor($campo);

        if ($valor === null || $valor === '') {
            $this->errores[] = "{$etiqueta} es obligatorio.";
        }

        return $this;
    }

    public function longitudMaxima(string $campo, string $etiqueta, int $maximo)
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && mb_strlen($valor) > $maximo) {
            $this->errores[] = "{$etiqueta} no puede tener más de {$maximo} caracteres.";
        }

        return $this;
    }

    public function longitudMinima(string $campo, string $etiqueta, int $minimo)
    {
        $valor = $this->valor($campo);

        if (is_string($valor) && mb_strlen($valor) < $minimo) {
            $this->errores[] = "{$etiqueta} debe tener al menos {$minimo} caracteres.";
        }

        return $this;
    }

    public function numerico(string $campo, string $etiqueta)
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !is_numeric($valor)) {
            $this->errores[] = "{$etiqueta} debe ser un número.";
        }

        return $this;
    }

    /**
     * Dinero con hasta 2 decimales y un máximo: rechaza notación científica ("1e3"), comas, letras
     * y más de 2 decimales (antes MariaDB los redondeaba o recortaba en silencio).
     */
    public function precio(string $campo, string $etiqueta, float $maximo)
    {
        $valor = $this->valor($campo);

        if ($valor === null || $valor === '') {
            return $this;
        }

        $texto = is_string($valor) || is_int($valor) || is_float($valor) ? trim((string) $valor) : '';

        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/', $texto) !== 1) {
            $this->errores[] = "{$etiqueta} debe ser un número con máximo 2 decimales (ej. 25 o 25.50), sin comas, letras ni notación científica.";
        } elseif ((float) $texto > $maximo) {
            $this->errores[] = sprintf('%s no puede ser mayor a Q%s.', $etiqueta, number_format($maximo, 2, '.', ''));
        }

        return $this;
    }

    /** Fecha de calendario real en formato AAAA-MM-DD (rechaza "2026-02-30", "ayer" o 0000-00-00). */
    public function fecha(string $campo, string $etiqueta)
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && !self::esFechaValida((string) $valor)) {
            $this->errores[] = "{$etiqueta} no es una fecha válida (usa el calendario: AAAA-MM-DD).";
        }

        return $this;
    }

    public static function esFechaValida(string $fecha): bool
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha && $fecha >= '2000-01-01';
    }

    public function email(string $campo, string $etiqueta)
    {
        $valor = $this->valor($campo);

        if ($valor !== null && $valor !== '' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $this->errores[] = "{$etiqueta} no es un correo válido.";
        }

        return $this;
    }

    /** @param array<int,mixed> $valoresPermitidos */
    public function enQueValores(string $campo, string $etiqueta, array $valoresPermitidos)
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

    private function valor(string $campo)
    {
        return $this->datos[$campo] ?? null;
    }
}
