<?php
/**
 * @var array<string,mixed>|null $producto
 * @var array<int,string> $errores
 * @var array<int,array<string,mixed>> $categorias
 * @var array<int,array<string,mixed>> $temporadas
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $producto !== null && isset($producto['id']) && $producto['id'] !== null;
$accion = $esEdicion ? '/admin/productos/' . (int) $producto['id'] . '/editar' : '/admin/productos/crear';
$temporadaIdsSeleccionadas = $producto['temporada_ids'] ?? [];
?>

<h1><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h1>

<div class="tarjeta" style="max-width:720px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($accion, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
        <?= Csrf::campo() ?>

        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="120" required
                   value="<?= htmlspecialchars((string) ($producto['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="descripcion">Descripción / notas</label>
            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                   value="<?= htmlspecialchars((string) ($producto['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id" required>
                <option value="">-- Selecciona --</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($producto['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="precio">Precio (Q)</label>
            <input type="number" id="precio" name="precio" step="0.01" min="0.01" required
                   value="<?= htmlspecialchars((string) ($producto['precio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="precio_alt">Precio alternativo (Q) — opcional</label>
            <input type="number" id="precio_alt" name="precio_alt" step="0.01" min="0.01"
                   value="<?= htmlspecialchars((string) ($producto['precio_alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <span class="campo-ayuda">Ej. frappés con/sin café. Déjalo vacío si no aplica.</span>
        </div>

        <div class="campo">
            <label for="etiqueta_precio_alt">Etiqueta del precio alternativo</label>
            <input type="text" id="etiqueta_precio_alt" name="etiqueta_precio_alt" maxlength="40" placeholder="Ej. Sin café"
                   value="<?= htmlspecialchars((string) ($producto['etiqueta_precio_alt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="temperatura">Temperatura (para el carrito)</label>
            <?php $temperaturaActual = (string) ($producto['temperatura'] ?? ''); ?>
            <select id="temperatura" name="temperatura">
                <option value="" <?= $temperaturaActual === '' ? 'selected' : '' ?>>No aplica</option>
                <option value="ambas" <?= $temperaturaActual === 'ambas' ? 'selected' : '' ?>>Se puede pedir caliente o frío (el cliente elige)</option>
                <option value="caliente" <?= $temperaturaActual === 'caliente' ? 'selected' : '' ?>>Solo caliente</option>
                <option value="frio" <?= $temperaturaActual === 'frio' ? 'selected' : '' ?>>Solo frío</option>
            </select>
            <span class="campo-ayuda">Se muestra en el pedido de WhatsApp (ej. "Moka - Caliente"). Si eliges "caliente o frío", el cliente debe escoger al agregarlo.</span>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="permite_leche" name="permite_leche" value="1" <?= !empty($producto['permite_leche']) ? 'checked' : '' ?>>
            <label for="permite_leche" style="margin:0;">Permite elegir leche (el cliente la escoge en el carrito)</label>
        </div>
        <span class="campo-ayuda" style="display:block;margin:-6px 0 14px;">Para bebidas con leche. Las leches y sus precios se administran en <a href="/admin/leches">Leches</a>.</span>

        <div class="campo">
            <label for="imagen">Foto</label>
            <?php if (!empty($producto['imagen'])): ?>
                <div style="margin-bottom:8px;">
                    <img class="miniatura" src="<?= UPLOADS_URL ?>/productos/<?= htmlspecialchars($producto['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                </div>
            <?php endif; ?>
            <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp">
            <span class="campo-ayuda">JPG, PNG o WEBP, máximo 5 MB. Deja vacío para conservar la foto actual.</span>
        </div>

        <div class="campo">
            <label>Temporadas</label>
            <?php if ($temporadas === []): ?>
                <span class="campo-ayuda">No hay temporadas creadas todavía.</span>
            <?php endif; ?>
            <?php foreach ($temporadas as $t): ?>
                <div class="campo-checkbox">
                    <input type="checkbox" id="temporada_<?= (int) $t['id'] ?>" name="temporadas[]" value="<?= (int) $t['id'] ?>"
                           <?= in_array((int) $t['id'], $temporadaIdsSeleccionadas, true) ? 'checked' : '' ?>>
                    <label for="temporada_<?= (int) $t['id'] ?>" style="margin:0;"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="es_destacado" name="es_destacado" value="1" <?= (int) ($producto['es_destacado'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="es_destacado" style="margin:0;">Producto destacado</label>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="es_mas_vendido" name="es_mas_vendido" value="1" <?= (int) ($producto['es_mas_vendido'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="es_mas_vendido" style="margin:0;">Más vendido</label>
        </div>

        <div class="campo">
            <label for="orden">Orden</label>
            <input type="number" id="orden" name="orden" min="0" value="<?= (int) ($producto['orden'] ?? 0) ?>">
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1" <?= (!$esEdicion || (int) ($producto['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activo (visible en el sitio público)</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?></button>
        <a href="/admin/productos" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
