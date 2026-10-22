<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Producto
{
    /** Tope razonable para un producto de cafetería (en quetzales); también evita valores absurdos por error de captura. */
    public const PRECIO_MAXIMO = 500.0;

    /** @return array<int,array<string,mixed>> */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             ORDER BY c.orden ASC, p.orden ASC, p.nombre ASC'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Solo productos activos, para selects (ej. canje de lealtad). */
    public function listarActivos(): array
    {
        return Database::conexion()->query(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1
             ORDER BY c.orden ASC, p.orden ASC, p.nombre ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM productos WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        Database::conexion()->prepare(
            'INSERT INTO productos
                (categoria_id, nombre, descripcion, precio, precio_alt, etiqueta_precio_alt,
                 imagen, es_destacado, es_mas_vendido, activo, orden)
             VALUES
                (:categoria_id, :nombre, :descripcion, :precio, :precio_alt, :etiqueta_precio_alt,
                 :imagen, :es_destacado, :es_mas_vendido, :activo, :orden)'
        )->execute($datos);

        return (int) Database::conexion()->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        Database::conexion()->prepare(
            'UPDATE productos SET
                categoria_id = :categoria_id, nombre = :nombre, descripcion = :descripcion,
                precio = :precio, precio_alt = :precio_alt, etiqueta_precio_alt = :etiqueta_precio_alt,
                imagen = :imagen, es_destacado = :es_destacado, es_mas_vendido = :es_mas_vendido,
                activo = :activo, orden = :orden
             WHERE id = :id'
        )->execute($datos + ['id' => $id]);
    }

    /**
     * Solo cambia `activo` — a propósito no reutiliza actualizar() con
     * la fila completa de buscarPorId(): PDO en modo nativo
     * (ATTR_EMULATE_PREPARES=false) lanza "Invalid parameter number"
     * si el array de parámetros trae claves de más (id, creado_en...)
     * que no están en la sentencia preparada. Bug real encontrado en
     * pruebas — ver docs/arquitectura.md.
     */
    public function alternarActivo(int $id, bool $activo): void
    {
        Database::conexion()
            ->prepare('UPDATE productos SET activo = :activo WHERE id = :id')
            ->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public function eliminar(int $id): void
    {
        Database::conexion()->prepare('DELETE FROM productos WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Lo que impide borrar un producto: canjes de lealtad ya hechos (historial y recompensas) y promociones que lo usan
     * (borrarlo las eliminaría en silencio). Los pedidos NO lo impiden: guardan su propio nombre y precio.
     *
     * @return array<string,int> motivo => cantidad (solo los que son mayores que cero)
     */
    public function referenciasQueImpidenBorrar(int $id): array
    {
        $pdo = Database::conexion();
        $consultas = [
            'canje(s) de recompensas' => 'SELECT COUNT(*) FROM recompensas WHERE producto_canjeado_id = :id',
            'promoción(es)'           => 'SELECT COUNT(*) FROM promociones WHERE producto_id = :id',
        ];
        $salida = [];
        foreach ($consultas as $nombre => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $n = (int) $stmt->fetchColumn();
            if ($n > 0) {
                $salida[$nombre] = $n;
            }
        }

        return $salida;
    }

    /** Referencias que impiden borrado físico (canjes de lealtad ya hechos con este producto). */
    public function tieneReferenciasDeLealtad(int $id): bool
    {
        $stmt = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM lealtad_movimientos WHERE producto_canjeado_id = :id'
        );
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function contarActivos(): int
    {
        return (int) Database::conexion()->query('SELECT COUNT(*) FROM productos WHERE activo = 1')->fetchColumn();
    }

    // --- Consultas para el sitio público (Fase 7) ---

    /** @return array<int,array<string,mixed>> Productos activos agrupados por categoría (categorías activas, con al menos un producto activo). */
    public function listarMenuPublico(): array
    {
        $productos = Database::conexion()->query(
            'SELECT p.*, c.id AS cat_id, c.nombre AS cat_nombre, c.slug AS cat_slug, c.notas AS cat_notas
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND c.activo = 1
             ORDER BY c.orden ASC, p.orden ASC, p.nombre ASC'
        )->fetchAll();

        $menu = [];
        foreach ($productos as $p) {
            $catId = (int) $p['cat_id'];
            if (!isset($menu[$catId])) {
                $menu[$catId] = [
                    'id' => $catId, 'nombre' => $p['cat_nombre'], 'slug' => $p['cat_slug'],
                    'notas' => $p['cat_notas'], 'productos' => [],
                ];
            }
            $menu[$catId]['productos'][] = $p;
        }

        return array_values($menu);
    }

    /** @return array<int,array<string,mixed>> */
    public function listarMasVendidos(int $limite = 6): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND p.es_mas_vendido = 1
             ORDER BY p.orden ASC, p.nombre ASC
             LIMIT :limite'
        );
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listarDestacados(int $limite = 6): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p JOIN categorias c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND p.es_destacado = 1
             ORDER BY p.orden ASC, p.nombre ASC
             LIMIT :limite'
        );
        $stmt->bindValue('limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Productos activos ligados a alguna temporada vigente HOY (nunca vencida). */
    public function listarEnTemporadaVigente(): array
    {
        return Database::conexion()->query(
            'SELECT DISTINCT p.*, c.nombre AS categoria_nombre
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             JOIN producto_temporadas pt ON pt.producto_id = p.id
             JOIN temporadas t ON t.id = pt.temporada_id
             WHERE p.activo = 1 AND t.activo = 1
               AND CURDATE() BETWEEN t.fecha_inicio AND t.fecha_fin
             ORDER BY p.orden ASC, p.nombre ASC'
        )->fetchAll();
    }

    public function categoriaExiste(int $categoriaId): bool
    {
        $stmt = Database::conexion()->prepare('SELECT COUNT(*) FROM categorias WHERE id = :id');
        $stmt->execute(['id' => $categoriaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // --- Temporadas asociadas (producto_temporadas, N:M) ---

    /** @return array<int,int> IDs de temporada asociados a este producto. */
    public function obtenerTemporadaIds(int $productoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT temporada_id FROM producto_temporadas WHERE producto_id = :id'
        );
        $stmt->execute(['id' => $productoId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @param array<int,int> $temporadaIds */
    public function sincronizarTemporadas(int $productoId, array $temporadaIds): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM producto_temporadas WHERE producto_id = :id')->execute(['id' => $productoId]);

        if ($temporadaIds === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO producto_temporadas (producto_id, temporada_id) VALUES (:producto_id, :temporada_id)'
        );

        foreach (array_unique($temporadaIds) as $temporadaId) {
            $stmt->execute(['producto_id' => $productoId, 'temporada_id' => $temporadaId]);
        }
    }
}
