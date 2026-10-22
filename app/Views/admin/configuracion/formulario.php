<?php
/**
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 * @var array<int,array<string,mixed>> $categoriasEnvio
 * @var array<int,string> $errores
 * @var array<int,array<string,mixed>> $portada imágenes del fondo de la portada, en orden
 * @var int $portadaMax máximo de imágenes de portada
 * @var int $portadaLote cuántas se pueden subir en este momento (cupo restante, tope por envío)
 * @var int $portadaMaxBytes tamaño máximo por imagen
 * @var bool $optimiza si el servidor tiene GD para crear versiones reducidas
 * @var array<string,mixed> $sobre estructura de «Sobre nosotros» (intro, subtitulo, tarjetas, cierre, origen)
 * @var array{url:string,alt:string,propia:bool}|null $fotoGaleria la foto de la galería que se usa si no hay foto propia
 */
use App\Core\Csrf;
use App\Core\SobreNosotros;

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

    <?php
        // Nota para quien desarrolla, no para el administrador del negocio:
        // $optimiza en false significa que este servidor no tiene la
        // extensión GD de PHP, así que las imágenes de portada se guardan
        // tal cual, sin las versiones reducidas que aceleran el sitio en el
        // celular (funciona igual, solo más pesado; en Hostinger normalmente
        // está activa). No se imprime nada en pantalla a propósito.
    ?>

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

        <h2 id="sobre-nosotros">Sobre nosotros</h2>
        <p class="campo-ayuda" style="margin-top:0;">
            Así se arma la sección «Sobre nosotros» del sitio: una introducción, un subtítulo con tarjetas y una frase de cierre,
            más una foto. Cada parte es opcional.
            <?php if (($sobre['origen'] ?? '') === 'texto_anterior'): ?>
                <br><strong>Estos campos se rellenaron solos con el texto que ya tenía el sitio; no se cambió ninguna palabra.</strong>
                Se guardan en cuanto pulses «Guardar configuración».
            <?php elseif (($sobre['origen'] ?? '') === 'texto_anterior_libre'): ?>
                <br><strong>El texto anterior no tenía este formato, así que está completo dentro de «Introducción».</strong>
                Puedes separarlo en tarjetas y frase de cierre; no se pierde nada hasta que lo guardes.
            <?php endif; ?>
        </p>

        <div class="campo">
            <label for="nosotros_intro">Introducción</label>
            <textarea id="nosotros_intro" name="nosotros_intro" rows="6" maxlength="<?= SobreNosotros::MAX_INTRO ?>"><?= htmlspecialchars($sobre['intro'] ?? '', ENT_NOQUOTES, 'UTF-8') ?></textarea>
            <span class="campo-ayuda">Se muestra como párrafos, arriba. Separa los párrafos con una línea en blanco.</span>
        </div>

        <div class="campo">
            <label for="nosotros_subtitulo">Subtítulo de las tarjetas</label>
            <input type="text" id="nosotros_subtitulo" name="nosotros_subtitulo" maxlength="<?= SobreNosotros::MAX_SUBTITULO ?>"
                   value="<?= htmlspecialchars($sobre['subtitulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. Lo que nos distingue">
            <span class="campo-ayuda">Título centrado que va encima de las tarjetas (se le agregan los dos puntos solo).</span>
        </div>

        <fieldset class="nosotros-tarjetas" data-nosotros-tarjetas data-max="<?= SobreNosotros::MAX_TARJETAS ?>">
            <legend>Tarjetas <span class="campo-ayuda" data-nosotros-contador></span></legend>
            <p class="campo-ayuda" style="margin-top:0;">
                Cada tarjeta es un recuadro con un título en negrita y su descripción. Hasta <?= SobreNosotros::MAX_TARJETAS ?>.
                Si dejas el título vacío, esa tarjeta no se muestra. Usa ▲ ▼ para cambiar el orden.
            </p>
            <?php
            $tarjetasSobre = array_values($sobre['tarjetas'] ?? []);
            for ($n = 1; $n <= SobreNosotros::MAX_TARJETAS; $n++):
                $t = $tarjetasSobre[$n - 1] ?? ['titulo' => '', 'descripcion' => ''];
                $usada = ($t['titulo'] ?? '') !== '' || ($t['descripcion'] ?? '') !== '';
            ?>
                <div class="nosotros-tarjeta" data-nosotros-tarjeta<?= $usada ? '' : ' hidden' ?>>
                    <div class="nosotros-tarjeta-cabecera">
                        <strong class="nosotros-tarjeta-numero">Tarjeta <span data-nosotros-numero><?= $n ?></span></strong>
                        <span class="nosotros-tarjeta-botones">
                            <button type="button" class="btn btn-secundario btn-pequeno" data-nosotros-accion="subir" aria-label="Subir tarjeta">▲</button>
                            <button type="button" class="btn btn-secundario btn-pequeno" data-nosotros-accion="bajar" aria-label="Bajar tarjeta">▼</button>
                            <button type="button" class="btn btn-peligro btn-pequeno" data-nosotros-accion="quitar">Quitar</button>
                        </span>
                    </div>
                    <div class="campo">
                        <label for="nosotros_titulo_<?= $n ?>">Título</label>
                        <input type="text" id="nosotros_titulo_<?= $n ?>" name="nosotros_titulo_<?= $n ?>" maxlength="<?= SobreNosotros::MAX_TITULO ?>"
                               value="<?= htmlspecialchars((string) ($t['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. Calidad en cada extracción">
                    </div>
                    <div class="campo" style="margin-bottom:0;">
                        <label for="nosotros_desc_<?= $n ?>">Descripción</label>
                        <textarea id="nosotros_desc_<?= $n ?>" name="nosotros_desc_<?= $n ?>" rows="3" maxlength="<?= SobreNosotros::MAX_DESCRIPCION ?>"><?= htmlspecialchars((string) ($t['descripcion'] ?? ''), ENT_NOQUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
            <?php endfor; ?>
            <button type="button" class="btn btn-secundario" data-nosotros-agregar>+ Agregar tarjeta</button>
        </fieldset>

        <div class="campo">
            <label for="nosotros_cierre">Frase de cierre</label>
            <textarea id="nosotros_cierre" name="nosotros_cierre" rows="2" maxlength="<?= SobreNosotros::MAX_CIERRE ?>"><?= htmlspecialchars($sobre['cierre'] ?? '', ENT_NOQUOTES, 'UTF-8') ?></textarea>
            <span class="campo-ayuda">Frase corta, centrada y destacada al final de la sección.</span>
        </div>

        <div class="campo">
            <label for="sobre_nosotros_foto">Foto de la sección</label>
            <?php
            $fotoPropia = (string) ($config['sobre_nosotros_foto'] ?? '');
            $hayFotoPropia = $fotoPropia !== '' && is_file(UPLOADS_PATH . '/sitio/' . basename($fotoPropia));
            ?>
            <?php if ($hayFotoPropia): ?>
                <div style="margin-bottom:8px;"><img class="miniatura" id="nosotros-foto-actual" style="max-width:220px;" src="<?= UPLOADS_URL ?>/sitio/<?= htmlspecialchars(basename($fotoPropia), ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
            <?php else: ?>
                <p class="campo-ayuda" id="nosotros-foto-aviso" style="margin:0 0 8px;">
                    Sin foto propia: se usará una foto de la galería<?= $fotoGaleria === null ? ' (y ahora no hay fotos en la galería, así que la sección va solo con texto)' : '' ?>.
                </p>
            <?php endif; ?>
            <input type="file" id="sobre_nosotros_foto" name="sobre_nosotros_foto" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                   data-max-bytes="<?= (int) $portadaMaxBytes ?>">
            <span class="campo-ayuda"><?= $hayFotoPropia ? 'Elige otra para reemplazarla. ' : '' ?>JPG, PNG o WEBP, de hasta <?= (int) round($portadaMaxBytes / 1048576) ?> MB. Mejor si es horizontal o cuadrada.</span>
            <?php if ($hayFotoPropia): ?>
                <label class="campo-checkbox" style="margin-top:8px;min-height:44px;">
                    <input type="checkbox" name="quitar_sobre_nosotros_foto" value="1" id="quitar_sobre_nosotros_foto">
                    Quitar la foto (el sitio usará una de la galería)
                </label>
            <?php endif; ?>
        </div>
        <div class="campo">
            <label for="sobre_nosotros_foto_pie">Pie de foto (opcional)</label>
            <input type="text" id="sobre_nosotros_foto_pie" name="sobre_nosotros_foto_pie" maxlength="<?= SobreNosotros::MAX_PIE ?>"
                   value="<?= htmlspecialchars($config['sobre_nosotros_foto_pie'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. Nuestro espacio, listo para recibirte">
            <span class="campo-ayuda">Texto <strong>visible</strong> debajo de la foto, en el sitio. Si lo dejas vacío no se muestra nada.</span>
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
            <label for="whatsapp_flotante_texto">Texto de la burbuja del botón flotante de WhatsApp</label>
            <input type="text" id="whatsapp_flotante_texto" name="whatsapp_flotante_texto" maxlength="60"
                   placeholder="¿Tienes alguna duda? ¡Escríbenos! 👋"
                   value="<?= htmlspecialchars($config['whatsapp_flotante_texto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <span class="campo-ayuda">
                Aparece junto al botón redondo de WhatsApp que sigue al visitante por toda la página. Usa una
                frase corta que dé ganas de escribir; si se deja vacío se muestra "¿Tienes alguna duda?
                ¡Escríbenos! 👋".
            </span>
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
            <label for="mapa_coordenadas">Coordenadas exactas del local (para el mapa)</label>
            <input type="text" id="mapa_coordenadas" name="mapa_coordenadas" maxlength="40"
                   placeholder="15.0208512, -91.1474688" pattern="-?[0-9]{1,3}(\.[0-9]+)?\s*,\s*-?[0-9]{1,3}(\.[0-9]+)?"
                   value="<?= htmlspecialchars($config['mapa_coordenadas'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <span class="campo-ayuda">Se llenan solas al guardar un enlace nuevo de Google Maps (abajo), para que el mapa quede exactamente donde apunta ese enlace. Solo escríbelas a mano si necesitas otro punto. Si se dejan vacías, el mapa busca por la dirección.</span>
        </div>
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
            <input type="number" id="umbral_lealtad" name="umbral_lealtad" min="1" max="100" step="1"
                   value="<?= htmlspecialchars($config['umbral_lealtad'] ?? '10', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <h2>Carrito de pedidos</h2>
        <div class="campo">
            <label for="carrito_aviso_ubicacion">Aviso de ubicación (se muestra en el carrito, antes de enviar por WhatsApp)</label>
            <textarea id="carrito_aviso_ubicacion" name="carrito_aviso_ubicacion" rows="2" maxlength="300"><?= htmlspecialchars(\App\Models\Configuracion::avisoUbicacion($config), ENT_NOQUOTES, 'UTF-8') ?></textarea>
            <p class="campo-ayuda">El cliente debe marcar «Entendido» para poder enviar el pedido. Si lo dejas vacío se usa: «<?= htmlspecialchars(\App\Models\Configuracion::AVISO_UBICACION_DEFECTO, ENT_QUOTES, 'UTF-8') ?>»</p>
        </div>

        <?php
        $diasPedidos = \App\Core\PedidosDisponibles::diasActivos($config);
        $nombresPedidos = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];
        $estadoPedidosHoy = \App\Core\PedidosDisponibles::estado($config);
        ?>
        <h3 style="margin-bottom:6px;">¿Cuándo se reciben pedidos por la página?</h3>
        <input type="hidden" name="pedidos_config_enviada" value="1">
        <p class="campo-ayuda" style="margin-top:0;">
            Los días que desmarques el menú se sigue viendo, pero no se puede agregar al carrito ni enviar pedidos
            (por ejemplo, los días en que no trabaja el repartidor). El cliente ve el aviso y el próximo día en que se reciben.
        </p>
        <div class="campo" style="display:flex;gap:8px 18px;flex-wrap:wrap;">
            <?php foreach ($nombresPedidos as $numeroPedido => $etiquetaPedido): ?>
                <label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;">
                    <input type="checkbox" name="pedidos_dia[<?= (int) $numeroPedido ?>]" value="1" <?= in_array($numeroPedido, $diasPedidos, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($etiquetaPedido, ENT_QUOTES, 'UTF-8') ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="campo">
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;">
                <input type="checkbox" name="pedidos_pausa" value="1" <?= \App\Core\PedidosDisponibles::pausado($config) ? 'checked' : '' ?>>
                Pausar los pedidos ahora mismo (imprevistos o falta de personal)
            </label>
            <label for="pedidos_mensaje" style="margin-top:10px;">Mensaje para el cliente mientras estén pausados (opcional)</label>
            <input type="text" id="pedidos_mensaje" name="pedidos_mensaje" maxlength="200"
                   value="<?= htmlspecialchars($config['pedidos_mensaje'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="<?= htmlspecialchars(\App\Core\PedidosDisponibles::MENSAJE_PAUSA_DEFECTO, ENT_QUOTES, 'UTF-8') ?>">
            <p class="campo-ayuda">Hoy: <strong><?= $estadoPedidosHoy['abierto'] ? 'se reciben pedidos' : htmlspecialchars($estadoPedidosHoy['mensaje'], ENT_QUOTES, 'UTF-8') ?></strong></p>
        </div>

        <?php
        $reglasEnvio = \App\Core\EnvioPedidos::reglas($config);
        ?>
        <h3 style="margin-bottom:6px;">Envío a domicilio</h3>
        <input type="hidden" name="envio_config_enviada" value="1">
        <p class="campo-ayuda" style="margin-top:0;">
            El envío se suma al total del pedido como una línea aparte. Si el cliente pide suficientes <strong>bebidas</strong>,
            el envío es gratis. Los sándwiches, panes u otros productos no cuentan como bebidas, salvo que marques su categoría abajo.
        </p>
        <div class="campo campo-checkbox">
            <input type="checkbox" id="envio_cobrar" name="envio_cobrar" value="1" <?= $reglasEnvio['activo'] ? 'checked' : '' ?>>
            <label for="envio_cobrar" style="margin:0;">Cobrar envío a domicilio</label>
        </div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div class="campo" style="flex:1;min-width:200px;">
                <label for="envio_costo">Costo del envío (Q)</label>
                <input type="text" id="envio_costo" name="envio_costo" inputmode="decimal" maxlength="9"
                       value="<?= htmlspecialchars(number_format($reglasEnvio['costo_centavos'] / 100, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                <span class="campo-ayuda">Ej. 5.00. Con 0 no se cobra envío.</span>
            </div>
            <div class="campo" style="flex:1;min-width:200px;">
                <label for="envio_gratis_desde">Envío gratis desde (bebidas)</label>
                <input type="number" id="envio_gratis_desde" name="envio_gratis_desde" min="0" max="<?= (int) \App\Core\EnvioPedidos::GRATIS_DESDE_MAXIMO ?>" step="1"
                       value="<?= (int) $reglasEnvio['gratis_desde'] ?>">
                <span class="campo-ayuda">Ej. 5 = a partir de 5 bebidas. Con 0 el envío nunca es gratis.</span>
            </div>
        </div>
        <div class="campo">
            <label>Categorías que cuentan como bebidas para el envío gratis</label>
            <div style="display:flex;gap:6px 20px;flex-wrap:wrap;">
                <?php foreach ($categoriasEnvio as $categoriaEnvio): ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;white-space:nowrap;<?= (int) $categoriaEnvio['activo'] === 1 ? '' : 'opacity:.55;' ?>">
                        <input type="checkbox" style="width:auto;flex:none;margin:0;" name="envio_categoria[<?= (int) $categoriaEnvio['id'] ?>]" value="1" <?= isset($reglasEnvio['categorias'][(int) $categoriaEnvio['id']]) ? 'checked' : '' ?>>
                        <?= htmlspecialchars((string) $categoriaEnvio['nombre'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <span class="campo-ayuda">Una categoría nueva (por ejemplo, roles de canela) queda sin marcar: si quieres que cuente, márcala aquí.</span>
        </div>

        <h2>Horario de atención</h2>
        <div class="campo">
            <label for="horario_agrupar">Cómo se muestra en el sitio</label>
            <select id="horario_agrupar" name="horario_agrupar">
                <?php $agrupar = ($config['horario_agrupar'] ?? '1') !== '0'; ?>
                <option value="1"<?= $agrupar ? ' selected' : '' ?>>Juntar los días seguidos con el mismo horario (ej. «Lunes a Viernes»)</option>
                <option value="0"<?= $agrupar ? '' : ' selected' ?>>Mostrar cada día por separado</option>
            </select>
            <span class="campo-ayuda">Solo cambia cómo se ve en el pie del sitio; los horarios de cada día de abajo no se tocan.
                Si algún día tiene un horario distinto, aparece en su propia fila.</span>
        </div>
        <p class="campo-ayuda" style="margin-top:0;">
            El segundo turno es para un día con horario partido (ej. abre 9:00-14:00, cierra a
            almorzar, y vuelve a abrir 16:00-21:00). Se deja vacío en los días que no lo tienen.
            Para una jornada corrida (sin pausa), pon solo el primer turno (ej. 9:00 a 21:00) y deja el segundo vacío.
        </p>
        <?php
            // La base de datos guarda TIME con segundos ("09:00:00"); un <input
            // type="time"> sin segundos al que se le da ese valor completo los
            // conserva y los vuelve a enviar tal cual al guardar ("09:00:00"),
            // lo que no pasaba la validación del servidor (que solo acepta
            // "HH:MM") y bloqueaba el guardado de TODO el formulario, no solo
            // del horario. Se recorta a 5 caracteres al imprimir el value.
            $hora5 = fn (?string $v): string => substr((string) ($v ?? ''), 0, 5);
        ?>
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
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de apertura (turno 1)" name="horario[<?= $numero ?>][apertura]" value="<?= htmlspecialchars($hora5($horario['hora_apertura'] ?? null), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                    a
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de cierre (turno 1)" name="horario[<?= $numero ?>][cierre]" value="<?= htmlspecialchars($hora5($horario['hora_cierre'] ?? null), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                </span>
            </div>
            <div class="campo-checkbox horario-fila" style="margin-bottom:8px;">
                <span class="horario-dia" style="width:90px;font-weight:400;font-size:0.85rem;color:#6b5a4d;">+ turno 2</span>
                <span class="horario-horas">
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de apertura del turno 2 (opcional)" name="horario[<?= $numero ?>][apertura2]" value="<?= htmlspecialchars($hora5($horario['hora_apertura_2'] ?? null), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                    a
                    <input type="time" class="horario-hora" aria-label="<?= $nombreDia ?>: hora de cierre del turno 2 (opcional)" name="horario[<?= $numero ?>][cierre2]" value="<?= htmlspecialchars($hora5($horario['hora_cierre_2'] ?? null), ENT_QUOTES, 'UTF-8') ?>" style="width:auto;">
                </span>
            </div>
        <?php endforeach; ?>

        <div style="margin-top:16px;">
            <button type="submit" class="btn">Guardar configuración</button>
        </div>
    </form>
</div>

<?php require APP_PATH . '/Views/layouts/panel_footer.php'; ?>
