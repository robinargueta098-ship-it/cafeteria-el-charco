<?php
/**
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 * @var array<int,string> $errores
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$nombresDias = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
?>

<h1>Configuración del sitio</h1>

<div class="tarjeta" style="max-width:720px;">
    <?php if ($errores !== []): ?>
        <div class="alerta alerta-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/configuracion" enctype="multipart/form-data">
        <?= Csrf::campo() ?>

        <h2>Identidad</h2>
        <div class="campo">
            <label for="nombre_sitio">Nombre del sitio</label>
            <input type="text" id="nombre_sitio" name="nombre_sitio" maxlength="100"
                   value="<?= htmlspecialchars($config['nombre_sitio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="logo">Logo</label>
            <?php if (!empty($config['logo'])): ?>
                <div style="margin-bottom:8px;"><img class="miniatura" src="<?= UPLOADS_URL ?>/sitio/<?= htmlspecialchars($config['logo'], ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.webp">
            <span class="campo-ayuda">JPG, PNG o WEBP. Deja vacío para conservar el actual.</span>
        </div>
        <div class="campo">
            <label for="hero_imagen">Foto de portada (hero)</label>
            <?php if (!empty($config['hero_imagen'])): ?>
                <div style="margin-bottom:8px;"><img class="miniatura" src="<?= UPLOADS_URL ?>/sitio/<?= htmlspecialchars($config['hero_imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:120px;height:80px;"></div>
            <?php endif; ?>
            <input type="file" id="hero_imagen" name="hero_imagen" accept=".jpg,.jpeg,.png,.webp">
            <span class="campo-ayuda">Foto grande de portada del sitio público. JPG, PNG o WEBP.</span>
        </div>
        <div class="campo">
            <label for="frase_hero">Frase de bienvenida (portada)</label>
            <input type="text" id="frase_hero" name="frase_hero" maxlength="200"
                   value="<?= htmlspecialchars($config['frase_hero'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="descripcion_corta">Descripción breve (portada)</label>
            <textarea id="descripcion_corta" name="descripcion_corta" rows="2" maxlength="300"><?= htmlspecialchars($config['descripcion_corta'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="campo">
            <label for="direccion_texto">Dirección</label>
            <input type="text" id="direccion_texto" name="direccion_texto" maxlength="255"
                   value="<?= htmlspecialchars($config['direccion_texto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="sobre_nosotros_texto">Texto "Sobre nosotros"</label>
            <textarea id="sobre_nosotros_texto" name="sobre_nosotros_texto" rows="4"><?= htmlspecialchars($config['sobre_nosotros_texto'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <h2>Contacto y redes</h2>
        <div class="campo">
            <label for="whatsapp_numero">WhatsApp (con código de país, ej. +50233577968)</label>
            <input type="text" id="whatsapp_numero" name="whatsapp_numero" maxlength="20"
                   value="<?= htmlspecialchars($config['whatsapp_numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="instagram_usuario">Instagram / TikTok (con @)</label>
            <input type="text" id="instagram_usuario" name="instagram_usuario" maxlength="100"
                   value="<?= htmlspecialchars($config['instagram_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="facebook_nombre">Nombre de la página de Facebook</label>
            <input type="text" id="facebook_nombre" name="facebook_nombre" maxlength="100"
                   value="<?= htmlspecialchars($config['facebook_nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="facebook_url">Enlace a la página de Facebook</label>
            <input type="url" id="facebook_url" name="facebook_url" maxlength="255"
                   value="<?= htmlspecialchars($config['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <h2>Ubicación y calificación de Google</h2>
        <div class="campo">
            <label for="google_maps_embed">Código embed de Google Maps</label>
            <textarea id="google_maps_embed" name="google_maps_embed" rows="3"><?= htmlspecialchars($config['google_maps_embed'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="campo">
            <label for="google_calificacion">Calificación de Google (ej. 4.8)</label>
            <input type="text" id="google_calificacion" name="google_calificacion" maxlength="10"
                   value="<?= htmlspecialchars($config['google_calificacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="google_calificacion_url">Enlace a la ficha de Google</label>
            <input type="url" id="google_calificacion_url" name="google_calificacion_url" maxlength="255"
                   value="<?= htmlspecialchars($config['google_calificacion_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <h2>Lealtad</h2>
        <div class="campo">
            <label for="umbral_lealtad">Cafés necesarios para 1 gratis</label>
            <input type="number" id="umbral_lealtad" name="umbral_lealtad" min="1"
                   value="<?= htmlspecialchars($config['umbral_lealtad'] ?? '10', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <h2>Horario de atención</h2>
        <?php foreach ($nombresDias as $numero => $nombreDia): ?>
            <?php
                $horario = null;
                foreach ($horarios as $h) {
                    if ((int) $h['dia_semana'] === $numero) { $horario = $h; break; }
                }
                $cerrado = $horario === null || (int) $horario['cerrado'] === 1;
            ?>
            <div class="campo-checkbox" style="margin-bottom:4px;">
                <input type="checkbox" id="abierto_<?= $numero ?>" name="horario[<?= $numero ?>][abierto]" value="1" <?= !$cerrado ? 'checked' : '' ?>>
                <label for="abierto_<?= $numero ?>" style="margin:0;width:90px;"><?= $nombreDia ?></label>
                <input type="time" name="horario[<?= $numero ?>][apertura]" value="<?= htmlspecialchars((string) ($horario['hora_apertura'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                a
                <input type="time" name="horario[<?= $numero ?>][cierre]" value="<?= htmlspecialchars((string) ($horario['hora_cierre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
            </div>
        <?php endforeach; ?>

        <div style="margin-top:16px;">
            <button type="submit" class="btn">Guardar configuración</button>
        </div>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
