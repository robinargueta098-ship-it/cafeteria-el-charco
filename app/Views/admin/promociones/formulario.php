<?php
/**
 * @var array<string,mixed>|null $promocion
 * @var array<int,string> $errores
 * @var array<int,array<string,mixed>> $categorias
 * @var array<int,array<string,mixed>> $productos
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$esEdicion = $promocion !== null && isset($promocion['id']);
$accion = $esEdicion ? '/admin/promociones/' . (int) $promocion['id'] . '/editar' : '/admin/promociones/crear';

$alcanceActual = 'todo';
if (!empty($promocion['producto_id'])) {
    $alcanceActual = 'producto';
} elseif (!empty($promocion['categoria_id'])) {
    $alcanceActual = 'categoria';
}

$diasSeleccionados = [];
if (!empty($promocion['dias_semana'])) {
    $diasSeleccionados = array_map('intval', explode(',', (string) $promocion['dias_semana']));
}
$nombresDias = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
?>

<h1><?= $esEdicion ? 'Editar promoción' : 'Nueva promoción' ?></h1>

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
                   value="<?= htmlspecialchars((string) ($promocion['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="descripcion">Descripción</label>
            <input type="text" id="descripcion" name="descripcion" maxlength="255"
                   value="<?= htmlspecialchars((string) ($promocion['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="imagen">Foto (opcional)</label>
            <?php if (!empty($promocion['imagen'])): ?>
                <div style="margin-bottom:8px;">
                    <img class="miniatura" src="<?= UPLOADS_URL ?>/promociones/<?= htmlspecialchars($promocion['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                </div>
            <?php endif; ?>
            <input type="file" id="imagen" name="imagen" accept=".jpg,.jpeg,.png,.webp">
            <span class="campo-ayuda">JPG, PNG o WEBP, máximo 5 MB. Se muestra en la sección "Promociones" del sitio; sin foto, la tarjeta se ve igual pero sin imagen. Deja vacío para conservar la foto actual.</span>
        </div>

        <div class="campo">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo" required>
                <?php
                $tipos = [
                    'descuento_porcentaje' => 'Descuento porcentual',
                    'descuento_monto'      => 'Descuento fijo (Q)',
                    'dos_por_uno'          => '2x1',
                    'producto_gratis'      => 'Producto gratis',
                ];
                foreach ($tipos as $valorTipo => $etiqueta):
                ?>
                    <option value="<?= $valorTipo ?>" <?= ($promocion['tipo'] ?? '') === $valorTipo ? 'selected' : '' ?>>
                        <?= $etiqueta ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo" id="grupo-valor">
            <label for="valor">Valor (% o Q según el tipo)</label>
            <input type="number" id="valor" name="valor" step="0.01" min="0.01"
                   value="<?= htmlspecialchars((string) ($promocion['valor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label>Alcance</label>
            <div class="campo-checkbox">
                <input type="radio" id="alcance_todo" name="alcance" value="todo" <?= $alcanceActual === 'todo' ? 'checked' : '' ?>>
                <label for="alcance_todo" style="margin:0;">Todo el menú</label>
            </div>
            <div class="campo-checkbox">
                <input type="radio" id="alcance_categoria" name="alcance" value="categoria" <?= $alcanceActual === 'categoria' ? 'checked' : '' ?>>
                <label for="alcance_categoria" style="margin:0;">Una categoría</label>
            </div>
            <div class="campo-checkbox">
                <input type="radio" id="alcance_producto" name="alcance" value="producto" <?= $alcanceActual === 'producto' ? 'checked' : '' ?>>
                <label for="alcance_producto" style="margin:0;">Un producto</label>
            </div>
        </div>

        <div class="campo" id="grupo-categoria">
            <label for="categoria_id">Categoría</label>
            <select id="categoria_id" name="categoria_id">
                <option value="">-- Selecciona --</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($promocion['categoria_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo" id="grupo-producto">
            <label for="producto_id">Producto</label>
            <select id="producto_id" name="producto_id">
                <option value="">-- Selecciona --</option>
                <?php foreach ($productos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($promocion['producto_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="fecha_inicio">Fecha de inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" required
                   value="<?= htmlspecialchars((string) ($promocion['fecha_inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="fecha_fin">Fecha de fin</label>
            <input type="date" id="fecha_fin" name="fecha_fin" required
                   value="<?= htmlspecialchars((string) ($promocion['fecha_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label>Días de la semana (vacío = todos los días)</label>
            <?php foreach ($nombresDias as $numero => $nombreDia): ?>
                <div class="campo-checkbox">
                    <input type="checkbox" id="dia_<?= $numero ?>" name="dias_semana[]" value="<?= $numero ?>"
                           <?= in_array($numero, $diasSeleccionados, true) ? 'checked' : '' ?>>
                    <label for="dia_<?= $numero ?>" style="margin:0;"><?= $nombreDia ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="campo">
            <label for="hora_inicio">Hora de inicio (opcional)</label>
            <input type="time" id="hora_inicio" name="hora_inicio"
                   value="<?= htmlspecialchars((string) ($promocion['hora_inicio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="hora_fin">Hora de fin (opcional)</label>
            <input type="time" id="hora_fin" name="hora_fin"
                   value="<?= htmlspecialchars((string) ($promocion['hora_fin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="campo">
            <label for="prioridad">Prioridad</label>
            <input type="number" id="prioridad" name="prioridad" value="<?= (int) ($promocion['prioridad'] ?? 0) ?>">
            <span class="campo-ayuda">Si dos promociones vigentes aplican al mismo producto, gana la de mayor prioridad — nunca se suman. También es el orden en que se muestran en el sitio: puedes cambiarlo con las flechas ▲ ▼ del listado en vez de escribir un número.</span>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="combinable_con_lealtad" name="combinable_con_lealtad" value="1"
                   <?= (int) ($promocion['combinable_con_lealtad'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="combinable_con_lealtad" style="margin:0;">Se puede combinar con el programa de lealtad</label>
        </div>

        <div class="campo campo-checkbox">
            <input type="checkbox" id="activo" name="activo" value="1" <?= (!$esEdicion || (int) ($promocion['activo'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label for="activo" style="margin:0;">Activa</label>
        </div>

        <button type="submit" class="btn"><?= $esEdicion ? 'Guardar cambios' : 'Crear promoción' ?></button>
        <a href="/admin/promociones" class="btn btn-secundario">Cancelar</a>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
