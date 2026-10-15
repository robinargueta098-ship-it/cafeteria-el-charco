<?php

declare(strict_types=1);

namespace App\Core;

use PDOException;

/**
 * Permisos por rol y módulo. El Administrador (rol 1) siempre puede todo; para los demás roles, el Administrador
 * decide en «Roles y permisos» qué módulos ve y cuáles puede editar. Niveles: ninguno < ver < editar.
 *
 * Usuarios, Configuración, Auditoría y Roles y permisos NO se delegan nunca (solo Administrador), igual que las
 * acciones sensibles marcadas en cada controlador (corregir saldos, eliminar clientes, reportes de ventas, etc.).
 *
 * Si la tabla `rol_permisos` no existe o un rol no tiene filas, se usan los valores por defecto de abajo
 * (los mismos que tenían Cajero y Repartidor antes de que esto fuera configurable).
 * Compatible con PHP 7.4.
 */
class Permisos
{
    public const NINGUNO = 0;
    public const VER = 1;
    public const EDITAR = 2;

    public const ROL_ADMINISTRADOR = 1;

    private const NOMBRES = ['ninguno' => self::NINGUNO, 'ver' => self::VER, 'editar' => self::EDITAR];

    /**
     * módulo => [etiqueta, nivel máximo, explicación de «Editar»]
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    public const MODULOS = [
        'pedidos'     => ['Pedidos', 'editar', 'Ver: consulta los pedidos. Editar: confirma, entrega o cancela.'],
        'reportes'    => ['Reportes (Pedidos y Mis consumos)', 'ver', 'Solo consulta. Los reportes de ventas, ajustes y catálogo son siempre del Administrador.'],
        'clientes'    => ['Clientes', 'editar', 'Ver: consulta. Editar: crea y modifica clientes (eliminar es del Administrador).'],
        'lealtad'     => ['Lealtad', 'editar', 'Ver: consulta saldos. Editar: registra consumos y restablece PIN (corregir saldos es del Administrador).'],
        'canjear'     => ['Escanear código', 'editar', 'Ver: abre la pantalla. Editar: suma consumos y canjea recompensas y cupones.'],
        'recompensas' => ['Recompensas', 'ver', 'Solo consulta.'],
        'cupones'     => ['Cupones', 'editar', 'Ver: consulta. Editar: cancela cupones.'],
        'productos'   => ['Productos', 'editar', 'Ver: consulta. Editar: crea, modifica y activa o desactiva.'],
        'categorias'  => ['Categorías', 'editar', 'Ver: consulta. Editar: crea, modifica y elimina.'],
        'galeria'     => ['Galería', 'editar', 'Ver: consulta. Editar: sube, ordena y elimina fotos.'],
        'temporadas'  => ['Temporadas', 'editar', 'Ver: consulta. Editar: crea, modifica y ordena.'],
        'promociones' => ['Promociones', 'editar', 'Ver: consulta. Editar: crea, modifica y ordena.'],
    ];

    /** Lo que cada rol tenía antes de que los permisos fueran configurables. @var array<int,array<string,string>> */
    private const DEFECTOS = [
        2 => ['pedidos' => 'editar', 'reportes' => 'ver', 'clientes' => 'editar', 'lealtad' => 'editar', 'canjear' => 'editar',
              'recompensas' => 'ver', 'cupones' => 'ver'],
        3 => ['pedidos' => 'editar'],
    ];

    /** @var array<int,array<string,int>> */
    private static $cache = [];

    /** Nivel (0, 1 o 2) que el rol tiene sobre el módulo. */
    public static function nivel(int $rolId, string $modulo): int
    {
        if ($rolId === self::ROL_ADMINISTRADOR) {
            return self::EDITAR;
        }
        if (!isset(self::MODULOS[$modulo])) {
            return self::NINGUNO;
        }
        $nivel = self::cargar($rolId)[$modulo] ?? self::NINGUNO;
        $maximo = self::NOMBRES[self::MODULOS[$modulo][1]];

        return min($nivel, $maximo);
    }

    /** ¿El usuario en sesión puede 'ver' o 'editar' el módulo? */
    public static function puede(string $modulo, string $nivel = 'ver'): bool
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            return false;
        }

        return self::nivel((int) $usuario['rol_id'], $modulo) >= (self::NOMBRES[$nivel] ?? self::EDITAR);
    }

    /** Primera pantalla del panel a la que el rol tiene acceso (para dirigirlo al entrar). */
    public static function primeraRuta(int $rolId): string
    {
        foreach (['pedidos' => '/admin/pedidos', 'clientes' => '/admin/clientes', 'lealtad' => '/admin/lealtad', 'canjear' => '/admin/lealtad/canjear',
                  'recompensas' => '/admin/recompensas', 'cupones' => '/admin/cupones', 'productos' => '/admin/productos',
                  'reportes' => '/admin/reportes'] as $modulo => $ruta) {
            if (self::nivel($rolId, $modulo) >= self::VER) {
                return $ruta;
            }
        }

        return '/admin';
    }

    /**
     * Niveles actuales de un rol para todos los módulos (con los valores por defecto donde no hay fila).
     *
     * @return array<string,string> módulo => 'ninguno'|'ver'|'editar'
     */
    public static function delRol(int $rolId): array
    {
        $salida = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $salida[$modulo] = array_search(self::nivel($rolId, $modulo), self::NOMBRES, true);
        }

        return $salida;
    }

    /**
     * Guarda los permisos de un rol (reemplaza todos). Valores fuera de lo permitido se descartan.
     *
     * @param array<string,string> $niveles módulo => 'ninguno'|'ver'|'editar'
     * @return array<string,string> lo que quedó guardado
     */
    public static function guardar(int $rolId, array $niveles): array
    {
        $final = [];
        foreach (self::MODULOS as $modulo => $def) {
            $pedido = isset($niveles[$modulo]) ? (string) $niveles[$modulo] : 'ninguno';
            if (!isset(self::NOMBRES[$pedido])) {
                $pedido = 'ninguno';
            }
            if (self::NOMBRES[$pedido] > self::NOMBRES[$def[1]]) {
                $pedido = $def[1];
            }
            $final[$modulo] = $pedido;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM rol_permisos WHERE rol_id = :rol')->execute(['rol' => $rolId]);
            $ins = $pdo->prepare('INSERT INTO rol_permisos (rol_id, modulo, nivel) VALUES (:rol, :modulo, :nivel)');
            foreach ($final as $modulo => $nivel) {
                $ins->execute(['rol' => $rolId, 'modulo' => $modulo, 'nivel' => $nivel]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        unset(self::$cache[$rolId]);

        return $final;
    }

    /** @return array<string,int> */
    private static function cargar(int $rolId): array
    {
        if (isset(self::$cache[$rolId])) {
            return self::$cache[$rolId];
        }

        $niveles = [];
        try {
            $stmt = Database::conexion()->prepare('SELECT modulo, nivel FROM rol_permisos WHERE rol_id = :rol');
            $stmt->execute(['rol' => $rolId]);
            foreach ($stmt->fetchAll() as $f) {
                if (isset(self::NOMBRES[$f['nivel']])) {
                    $niveles[$f['modulo']] = self::NOMBRES[$f['nivel']];
                }
            }
        } catch (PDOException $e) {
            $niveles = [];   // la tabla todavía no existe: se usan los valores por defecto
        }

        if ($niveles === []) {
            foreach (self::DEFECTOS[$rolId] ?? [] as $modulo => $nivel) {
                $niveles[$modulo] = self::NOMBRES[$nivel];
            }
        }

        return self::$cache[$rolId] = $niveles;
    }
}
