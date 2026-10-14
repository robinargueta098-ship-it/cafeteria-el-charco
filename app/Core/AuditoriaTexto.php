<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Convierte una fila de `bitacora_auditoria` en una frase que se entienda sin conocer la base de datos:
 * «Eliminó el usuario "Robin" (Cajero)», «Editó el producto "Frappé de caramelo": precio: Q25 → Q30»…
 * Solo presentación: no cambia lo que se guarda.
 */
class AuditoriaTexto
{
    /** tabla => [singular con artículo, nombre del módulo] */
    private const OBJETOS = [
        'usuarios'            => ['el usuario', 'Usuarios'],
        'categorias'          => ['la categoría', 'Categorías'],
        'productos'           => ['el producto', 'Productos'],
        'promociones'         => ['la promoción', 'Promociones'],
        'temporadas'          => ['la temporada', 'Temporadas'],
        'galeria'             => ['la foto de la galería', 'Galería'],
        'portada_imagenes'    => ['la imagen de portada', 'Portada'],
        'clientes'            => ['al cliente', 'Clientes'],
        'configuracion_sistema' => ['la configuración', 'Configuración'],
        'cupones_promocion'   => ['el cupón', 'Cupones'],
        'recompensas'         => ['la recompensa', 'Recompensas'],
        'lealtad_movimientos' => ['los cafés', 'Lealtad'],
        'pedidos'             => ['el pedido', 'Pedidos'],
    ];

    private const CAMPOS = [
        'nombre' => 'nombre', 'descripcion' => 'descripción', 'precio' => 'precio', 'precio_alt' => 'precio alterno',
        'activo' => 'estado', 'orden' => 'orden', 'imagen' => 'imagen', 'categoria_id' => 'categoría',
        'producto_id' => 'producto', 'tipo' => 'tipo', 'valor' => 'valor', 'fecha_inicio' => 'fecha de inicio',
        'fecha_fin' => 'fecha de fin', 'prioridad' => 'prioridad', 'telefono' => 'teléfono', 'email' => 'correo',
        'notas' => 'notas', 'titulo' => 'título', 'temperatura' => 'temperatura', 'rol_id' => 'rol',
        'usuario' => 'usuario', 'etiqueta_personalizada' => 'texto de la etiqueta', 'destacado' => 'destacado',
        'mas_vendido' => 'más vendido', 'estado' => 'estado', 'motivo' => 'motivo',
    ];

    public static function modulo(string $tabla): string
    {
        return self::OBJETOS[$tabla][1] ?? ucfirst(str_replace('_', ' ', $tabla));
    }

    /** @param array<string,mixed> $fila fila de la bitácora (con cliente_nombre opcional) */
    public static function describir(array $fila): string
    {
        $tabla = (string) $fila['tabla_afectada'];
        $accion = (string) $fila['accion'];
        $antes = json_decode((string) ($fila['valores_antes'] ?? ''), true) ?: [];
        $despues = json_decode((string) ($fila['valores_despues'] ?? ''), true) ?: [];
        $datos = $despues + $antes;
        $objeto = isset($datos['_objeto']) ? (string) $datos['_objeto'] : '';
        if ($objeto === '') {
            // Registros guardados antes de que existiera `_objeto`: el nombre suele venir en los propios datos.
            foreach (['nombre', 'titulo', 'usuario', 'codigo'] as $campo) {
                if (isset($datos[$campo]) && is_string($datos[$campo]) && trim($datos[$campo]) !== '') {
                    $objeto = trim($datos[$campo]);
                    break;
                }
            }
        }
        $cliente = (string) ($fila['cliente_nombre'] ?? '');
        $sujeto = self::OBJETOS[$tabla][0] ?? 'el registro';

        // --- Casos con frase propia ---
        if ($tabla === 'configuracion_sistema' && !empty($despues['detalle']) && is_string($despues['detalle'])) {
            return $despues['detalle'];
        }
        if ($tabla === 'clientes' && ($despues['accion'] ?? '') === 'restablecer_pin') {
            return 'Restableció el PIN de la cuenta del cliente' . ($cliente !== '' ? ' «' . $cliente . '»' : '') . ' (PIN temporal entregado en persona)';
        }
        if ($tabla === 'lealtad_movimientos') {
            $quien = $cliente !== '' ? ' a «' . $cliente . '»' : '';
            $motivo = !empty($despues['motivo']) ? ' — Motivo: ' . $despues['motivo'] : '';
            if ($accion === 'consumo') {
                return 'Registró ' . (int) ($despues['cantidad'] ?? 0) . ' café(s)' . $quien
                    . (!empty($despues['codigos_generados']) ? ' (generó ' . count((array) $despues['codigos_generados']) . ' recompensa(s))' : '') . $motivo;
            }
            if ($accion === 'ajuste') {
                $verbo = ($despues['tipo'] ?? '') === 'ajuste_negativo' ? 'Restó' : 'Sumó';
                return $verbo . ' ' . (int) ($despues['cantidad'] ?? 0) . ' café(s) (ajuste manual)' . $quien . $motivo;
            }
        }
        if ($tabla === 'pedidos' && isset($despues['estado'])) {
            $etiquetas = ['enviado' => 'Enviado', 'confirmado' => 'Confirmado', 'entregado' => 'Entregado', 'cancelado' => 'Cancelado', 'anulado' => 'Anulado'];
            $de = isset($antes['estado']) ? ($etiquetas[$antes['estado']] ?? $antes['estado']) : '';
            $a = $etiquetas[$despues['estado']] ?? $despues['estado'];
            $codigo = (string) ($despues['codigo'] ?? $objeto);

            return 'Pedido' . ($codigo !== '' ? ' #' . $codigo : '') . ': ' . ($de !== '' ? $de . ' → ' : '') . $a
                . (!empty($despues['motivo']) ? ' — Motivo: ' . $despues['motivo'] : '');
        }
        if ($tabla === 'recompensas' && $accion === 'canje') {
            $codigo = (string) ($despues['codigo'] ?? '');
            return 'Canjeó la recompensa' . ($codigo !== '' ? ' ' . $codigo : '') . ($cliente !== '' ? ' de «' . $cliente . '»' : '');
        }
        if ($tabla === 'cupones_promocion') {
            $codigo = (string) ($despues['codigo'] ?? $objeto);
            $promo = !empty($despues['promocion']) ? ' (' . $despues['promocion'] . ')' : '';
            if ($accion === 'canje') {
                return 'Canjeó el cupón' . ($codigo !== '' ? ' ' . $codigo : '') . $promo;
            }
            if (($despues['estado'] ?? '') === 'cancelado') {
                return 'Canceló el cupón' . ($codigo !== '' ? ' ' . $codigo : '') . (!empty($despues['motivo']) ? ' — Motivo: ' . $despues['motivo'] : '');
            }
        }

        // --- Frase general: verbo + qué + cuál ---
        $cual = $objeto !== '' ? ' «' . $objeto . '»' : ' #' . (int) $fila['registro_id'];

        if ($accion === 'crear') {
            $extra = $tabla === 'usuarios' ? self::detalleUsuario($despues) : '';
            return 'Creó ' . $sujeto . $cual . $extra;
        }
        if ($accion === 'eliminar') {
            $extra = $tabla === 'usuarios' ? self::detalleUsuario($antes) : '';
            return 'Eliminó ' . $sujeto . $cual . $extra;
        }
        if ($accion === 'editar') {
            $cambios = [];
            foreach ($despues as $campo => $nuevo) {
                if (str_starts_with((string) $campo, '_') || in_array($campo, ['detalle', 'accion'], true)) {
                    continue;
                }
                $cambios[] = (self::CAMPOS[$campo] ?? str_replace('_', ' ', (string) $campo)) . ': '
                    . self::valor((string) $campo, $antes[$campo] ?? null) . ' → ' . self::valor((string) $campo, $nuevo);
            }
            $resumen = $cambios === [] ? '' : ' — ' . implode('; ', array_slice($cambios, 0, 3)) . (count($cambios) > 3 ? '; y ' . (count($cambios) - 3) . ' más' : '');

            return 'Editó ' . $sujeto . $cual . $resumen;
        }

        return ucfirst($accion) . ' ' . $sujeto . $cual;
    }

    /** " (usuario: Bvilatorlo, rol: Cajero)" para identificar sin dudas a quién se refiere. */
    private static function detalleUsuario(array $datos): string
    {
        $partes = [];
        if (!empty($datos['usuario']) && is_string($datos['usuario'])) {
            $partes[] = 'usuario: ' . $datos['usuario'];
        }
        if (isset($datos['rol_id'])) {
            $partes[] = 'rol: ' . self::valor('rol_id', $datos['rol_id']);
        }

        return $partes === [] ? '' : ' (' . implode(', ', $partes) . ')';
    }

    /** Valor corto y legible para una frase. */
    private static function valor(string $campo, $v): string
    {
        if ($v === null || $v === '') {
            return '(vacío)';
        }
        if (is_array($v)) {
            return 'varios';
        }
        if ($campo === 'activo') {
            return (string) $v === '1' ? 'activo' : 'inactivo';
        }
        if ($campo === 'rol_id') {
            $rol = (int) $v;

            return $rol === 1 ? 'Administrador' : ($rol === 2 ? 'Cajero' : 'rol ' . $rol);
        }
        if (in_array($campo, ['precio', 'precio_alt'], true) && is_numeric($v)) {
            return 'Q' . rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        }
        $texto = (string) $v;

        return mb_strlen($texto) > 40 ? mb_substr($texto, 0, 37) . '…' : $texto;
    }

    /** Quién lo hizo: el usuario actual; si ya no existe (se eliminó), el nombre que quedó guardado. */
    public static function usuario(array $fila): string
    {
        $nombre = (string) ($fila['usuario_nombre'] ?? '');
        if ($nombre !== '') {
            return $nombre;
        }

        $datos = (json_decode((string) ($fila['valores_despues'] ?? ''), true) ?: []) + (json_decode((string) ($fila['valores_antes'] ?? ''), true) ?: []);
        if (!empty($datos['_por'])) {
            return $datos['_por'] . ' (usuario eliminado)';
        }

        return 'Sistema';
    }
}
