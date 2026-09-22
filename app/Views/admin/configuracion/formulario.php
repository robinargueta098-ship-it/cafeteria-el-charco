<?php
/**
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 * @var array<int,string> $errores
 * @var array<int,array<string,mixed>> $portada imágenes del fondo de la portada, en orden
 * @var int $portadaMax máximo de imágenes de portada
 * @var int $portadaLote cuántas se pueden subir en este momento (cupo restante, tope por envío)
 * @var int $portadaMaxBytes tamaño máximo por imagen
 * @var bool $optimiza si el servidor tiene GD para crear versiones reducidas
 */
use App\Core\Csrf;

require APP_PATH . '/Views/layouts/panel_header.php';

$nombresDias = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
$totalPortada = count($portada);
?>

<h1>Configuración del sitio</h1>

<?php
/*
 * Imágenes de portada. Va en su PROPIA tarjeta, fuera del formulario grande de
 * abajo: cada imagen necesita sus propios botones (subir, ▲▼, eliminar) y HTML
 * no permite formularios anidados. Cada acción se aplica al instante.
 */
?>
<div class="tarjeta" id="portada" style="max-width:720px;margin-bottom:24px;">
    <h2 style="margin-top:0;">Imágenes de portada</h2>
    <p class="campo-ayuda" style="margin-top:0;">
        Fondo de la portada del sitio público: las imágenes se alternan solas cada 5 segundos,
        con un fundido suave, detrás del título y los botones. Son independientes de la Galería.
        Lo ideal son fotos horizontales y bien iluminadas (sobre ellas va un filtro oscuro para que el texto se lea).
        <strong>Estas acciones se aplican al instante</strong>; no dependen del botón
        «Guardar configuración» de más abajo.
    </p>

    <?php if (!$optimiza): ?>
        <div class="alerta alerta-error" style="background:#fdf1cf;color:#7a5b00;border-color:#d9b84a;">
            Este servidor no tiene la extensión GD de PHP, así que las imágenes se guardan tal cual, sin las
            versiones reducidas que aceleran el sitio en el celular. Funciona igual, solo que más pesado.
            (En Hostinger normalmente está activa.)
        </div>
    <?php endif; ?>

    <p style="margin-bottom:8px;">
        <strong><?= $totalPortada ?> de <?= (int) $portadaMax ?></strong> imágenes
        <?php if ($totalPortada === 0): ?>
            — sin imágenes, la portada muestra un aviso de «pendiente» en lugar de fondo.
        <?php elseif ($totalPortada === 1): ?>
            — con una sola imagen el fondo queda fijo; agrega al menos una más para que se alternen.
        <?php endif; ?>
    </p>

    <?php if ($totalPortada > 0): ?>
        <div class="tabla-contenedor" style="margin-bottom:16px;">
            <table class="tabla tabla-apilada tabla-apilada-portada" role="table">
                <thead role="rowgroup"><tr role="row"><th role="columnheader">#</th><th role="columnheader">Imagen</th><th role="columnheader">Orden</th><th role="columnheader">Acciones</th></tr></thead>
                <tbody role="rowgroup">
                    <?php foreach ($portada as $indice => $p): ?>
                        <tr role="row">
                            <td class="c-num" role="cell"><?= $indice + 1 ?></td>
                            <td class="c-img" role="cell"><img class="miniatura miniatura-portada" src="<?= UPLOADS_URL ?>/portada/<?= htmlspecialchars($p['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen de portada <?= $indice + 1 ?>"></td>
                            <td class="c-orden" role="cell">
                                <div class="acciones-tabla">
                                    <?php foreach (['subir' => ['▲', 'Subir', $indice === 0], 'bajar' => ['▼', 'Bajar', $indice === $totalPortada - 1]] as $direccion => [$simbolo, $etiqueta, $deshabilitado]): ?>
                                        <form method="post" action="/admin/configuracion/portada/<?= (int) $p['id'] ?>/mover">
                                            <?= Csrf::campo() ?>
                                            <input type="hidden" name="direccion" value="<?= $direccion ?>">
                                            <button type="submit" class="btn btn-secundario btn-pequeno"
                                                    aria-label="<?= $etiqueta ?> imagen <?= $indice + 1 ?>"
                                                    <?= $deshabilitado ? 'disabled' : '' ?>><?= $simbolo ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="c-acciones" role="cell">
                                <form method="post" action="/admin/configuracion/portada/<?= (int) $p['id'] ?>/eliminar"
                                      onsubmit="return confirm('¿Eliminar esta imagen de la portada? No se puede deshacer.');">
                                    <?= Csrf::campo() ?>
                                    <button type="submit" class="btn btn-peligro btn-pequeno">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($portadaLote > 0): ?>
        <form method="post" action="/admin/configuracion/portada" enctype="multipart/form-data">
            <?= Csrf::campo() ?>
            <div class="campo">
                <label for="portada_imagenes">Agregar imágenes</label>
                <input type="file" id="portada_imagenes" name="imagenes[]" multiple required
                       accept=".jpg,.jpeg,.png,.webp"
                       data-subida-multiple data-resumen="portada-resumen-subida" data-boton="portada-boton-subir"
                       data-max-archivos="<?= (int) $portadaLote ?>"
                       data-max-bytes="<?= (int) $portadaMaxBytes ?>">
                <span class="campo-ayuda">
                    Puedes elegir varias a la vez (ahora puedes agregar hasta <?= (int) $portadaLote ?>).
                    JPG, PNG o WEBP, máximo <?= (int) round($portadaMaxBytes / 1024 / 1024) ?> MB cada una.
                    Se agregan al final; reordénalas con las flechas.
                </span>
                <div id="portada-resumen-subida" class="campo-ayuda" aria-live="polite"></div>
            </div>
            <button type="submit" class="btn" id="portada-boton-subir">Subir imágenes</button>
        </form>
    <?php else: ?>
        <p class="campo-ayuda">
            Llegaste al máximo de <?= (int) $portadaMax ?> imágenes. Elimina alguna para poder agregar otra.
        </p>
    <?php endif; ?>
</div>

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
            <label for="boton_whatsapp_texto">Texto del botón de WhatsApp de la portada</label>
            <input type="text" id="boton_whatsapp_texto" name="boton_whatsapp_texto" maxlength="30"
                   placeholder="Ordena ahora"
                   value="<?= htmlspecialchars($config['boton_whatsapp_texto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <span class="campo-ayuda">Si se deja vacío se muestra "Ordena ahora".</span>
        </div>
        <div class="campo">
            <label for="instagram_usuario">Instagram (con @)</label>
            <input type="text" id="instagram_usuario" name="instagram_usuario" maxlength="100"
                   value="<?= htmlspecialchars($config['instagram_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="campo">
            <label for="tiktok_usuario">TikTok (usuario o enlace)</label>
            <input type="text" id="tiktok_usuario" name="tiktok_usuario" maxlength="100"
                   placeholder="@elcharco o https://www.tiktok.com/@elcharco"
                   value="<?= htmlspecialchars($config['tiktok_usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
            <label for="google_maps_embed">Enlace para compartir de Google Maps</label>
            <input type="url" id="google_maps_embed" name="google_maps_embed" maxlength="255"
                   placeholder="https://maps.app.goo.gl/..."
                   value="<?= htmlspecialchars($config['google_maps_embed'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <span class="campo-ayuda">
                Es el enlace que da el botón "Compartir" de Google Maps (no un código embed). Se usa para el
                botón "Cómo llegar" del sitio. El mapa visual ya no necesita configurarse aquí: se genera solo
                a partir de la dirección de arriba.
            </span>
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
        <p class="campo-ayuda" style="margin-top:0;">
            El segundo turno es para un día con horario partido (ej. abre 9:00-14:00, cierra a
            almorzar, y vuelve a abrir 16:00-21:00). Se deja vacío en los días que no lo tienen.
        </p>
        <?php foreach ($nombresDias as $numero => $nombreDia): ?>
            <?php
                $horario = null;
                foreach ($horarios as $h) {
                    if ((int) $h['dia_semana'] === $numero) { $horario = $h; break; }
                }
                $cerrado = $horario === null || (int) $horario['cerrado'] === 1;
            ?>
            <div class="campo-checkbox horario-fila" style="margin-bottom:0;">
                <input type="checkbox" id="abierto_<?= $numero ?>" name="horario[<?= $numero ?>][abierto]" value="1" <?= !$cerrado ? 'checked' : '' ?>>
                <label for="abierto_<?= $numero ?>" class="horario-dia" style="margin:0;width:90px;"><?= $nombreDia ?></label>
                <?php /* En escritorio este contenedor no afecta el diseño (display: contents); en celular agrupa las dos horas en su propia línea. */ ?>
                <span class="horario-horas">
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de apertura (turno 1)" name="horario[<?= $numero ?>][apertura]" value="<?= htmlspecialchars((string) ($horario['hora_apertura'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                    a
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de cierre (turno 1)" name="horario[<?= $numero ?>][cierre]" value="<?= htmlspecialchars((string) ($horario['hora_cierre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                </span>
            </div>
            <div class="campo-checkbox horario-fila" style="margin-bottom:8px;">
                <span class="horario-dia" style="width:90px;font-weight:400;font-size:0.85rem;color:#6b5a4d;">+ turno 2</span>
                <span class="horario-horas">
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de apertura del turno 2 (opcional)" name="horario[<?= $numero ?>][apertura2]" value="<?= htmlspecialchars((string) ($horario['hora_apertura_2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                    a
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de cierre del turno 2 (opcional)" name="horario[<?= $numero ?>][cierre2]" value="<?= htmlspecialchars((string) ($horario['hora_cierre_2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                </span>
            </div>
        <?php endforeach; ?>

        <div style="margin-top:16px;">
            <button type="submit" class="btn">Guardar configuración</button>
        </div>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
