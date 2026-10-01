<?php
/**
 * Portada pública — Fase 7. Todas las secciones viven en una sola
 * página (patrón habitual para un sitio de cafetería), con anclas de
 * navegación. Contenido real desde la base de datos
 * (docs/negocio.md) — lo que el negocio todavía no ha proporcionado
 * se marca con Vista::pendiente(), nunca se inventa.
 *
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 * @var array<int,array<string,mixed>> $menu
 * @var array<int,array<string,mixed>> $masVendidos
 * @var array<int,array<string,mixed>> $destacados
 * @var array<int,array<string,mixed>> $temporadas
 * @var array<int,array<string,mixed>> $productosTemporada
 * @var array<int,array<string,mixed>> $galeria
 * @var array<int,array<string,mixed>> $portada
 * @var array<int,array<string,mixed>> $promociones
 * @var int $umbralLealtad
 */
use App\Core\ImagenOptimizada;
use App\Core\Vista;
use App\Core\WhatsApp;

require APP_PATH . '/Views/layouts/header_publico.php';

$nombreSitio = $config['nombre_sitio'] ?? 'Cafetería El Charco';
$frase = $config['frase_hero'] ?? '';
$descripcionCorta = $config['descripcion_corta'] ?? '';
$whatsappNumero = $config['whatsapp_numero'] ?? '';

/**
 * Fondo del hero: las imágenes de la tabla `portada_imagenes` (las que se
 * administran en Configuración), en el orden que se les dio. Son
 * independientes de la Galería. Con una sola imagen el fondo queda fijo;
 * sin ninguna se muestra el marcador de "pendiente". Cada imagen usa sus
 * versiones WebP reducidas cuando existen.
 *
 * @var array<int,array{src:string,srcset:string}> $diapositivasHero
 */
$diapositivasHero = [];
foreach ($portada as $imagenPortada) {
    $diapositivasHero[] = ImagenOptimizada::atributos(UPLOADS_PATH . '/portada', UPLOADS_URL . '/portada', $imagenPortada['imagen']);
}

// Formato de fecha ("20 de diciembre") y del beneficio de una promoción
// ("20% de descuento", "2x1"...): Vista::fechaLarga() / Vista::etiquetaPromocion(),
// compartidos con app/Views/public/cuenta/panel.php (aviso de lealtad, §4.19).

/**
 * Tarjeta de producto reutilizada en Más vendidos / Destacados /
 * Temporada / Menú, para no repetir el mismo bloque de HTML cuatro
 * veces con el riesgo de que se desincronicen.
 */
$tarjetaProducto = function (array $p) {
    ?>
    <article class="tarjeta-producto">
        <?= Vista::fotoOMarcador($p['imagen'] ?? null, UPLOADS_URL . '/productos', $p['nombre']) ?>
        <div class="tarjeta-producto-cuerpo">
            <p class="tarjeta-producto-nombre"><?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($p['descripcion'])): ?>
                <p class="tarjeta-producto-descripcion"><?= htmlspecialchars($p['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p class="tarjeta-producto-precio">
                Q<?= number_format((float) $p['precio'], 2) ?>
                <?php if (!empty($p['precio_alt'])): ?>
                    <small> · Q<?= number_format((float) $p['precio_alt'], 2) ?> (<?= htmlspecialchars($p['etiqueta_precio_alt'], ENT_QUOTES, 'UTF-8') ?>)</small>
                <?php endif; ?>
            </p>
            <?php if ((int) ($p['es_destacado'] ?? 0) === 1 || (int) ($p['es_mas_vendido'] ?? 0) === 1): ?>
                <div class="etiquetas-producto">
                    <?php if ((int) ($p['es_mas_vendido'] ?? 0) === 1): ?><span class="etiqueta etiqueta-mas-vendido">Más vendido</span><?php endif; ?>
                    <?php if ((int) ($p['es_destacado'] ?? 0) === 1): ?><span class="etiqueta etiqueta-destacado">Destacado</span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>

<!-- ===================== HERO / INICIO ===================== -->
<section class="hero" id="inicio">
    <?php if ($diapositivasHero !== []): ?>
        <?php /*
            Fondo decorativo y automático (sin flechas ni puntos): sitio.js hace
            el fundido entre fotos. La primera carga con prioridad alta; las
            demás NO traen src: sitio.js las carga una por una DESPUÉS de que la
            página terminó de cargar, así no compiten con el contenido. Sin
            JavaScript simplemente se ve la primera foto fija.
        */ ?>
        <div class="hero-fondo" aria-hidden="true" data-hero-fondo>
            <?php foreach ($diapositivasHero as $i => $d): ?>
                <?php if ($i === 0): ?>
                    <img class="hero-slide activo" alt="" sizes="(max-width: 767px) 65vw, 100vw" decoding="async" fetchpriority="high"
                         src="<?= htmlspecialchars($d['src'], ENT_QUOTES, 'UTF-8') ?>"
                         <?php if ($d['srcset'] !== ''): ?>srcset="<?= htmlspecialchars($d['srcset'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                <?php else: ?>
                    <img class="hero-slide" alt="" sizes="(max-width: 767px) 65vw, 100vw" decoding="async"
                         src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
                         data-src="<?= htmlspecialchars($d['src'], ENT_QUOTES, 'UTF-8') ?>"
                         <?php if ($d['srcset'] !== ''): ?>data-srcset="<?= htmlspecialchars($d['srcset'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="hero-overlay"></div>
    <div class="hero-contenido">
        <?php if ($diapositivasHero === []): ?>
            <div class="hero-marcador"><?= Vista::pendiente('fotografía de portada') ?></div>
        <?php endif; ?>
        <h1><?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>
            <?= $frase !== '' ? htmlspecialchars($frase, ENT_QUOTES, 'UTF-8') : Vista::pendiente('frase de bienvenida') ?>
        </p>
        <?php if ($descripcionCorta !== ''): ?>
            <p><?= htmlspecialchars($descripcionCorta, ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <p><?= Vista::pendiente('descripción breve de la cafetería') ?></p>
        <?php endif; ?>
        <div class="hero-botones">
            <a href="#menu" class="btn btn-ver-menu">Ver menú</a>
            <?php if ($whatsappNumero !== ''): ?>
                <?php $textoBotonWhatsapp = $config['boton_whatsapp_texto'] ?? ''; ?>
                <a href="<?= htmlspecialchars(WhatsApp::enlace($whatsappNumero, "Hola, quisiera hacer un pedido en {$nombreSitio}."), ENT_QUOTES, 'UTF-8') ?>"
                   class="btn btn-whatsapp" target="_blank" rel="noopener">
                    <?= Vista::iconoWhatsapp() ?>
                    <span><?= htmlspecialchars($textoBotonWhatsapp !== '' ? $textoBotonWhatsapp : 'Ordena ahora', ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===================== MÁS VENDIDOS ===================== -->
<?php if ($masVendidos !== []): ?>
<section class="sitio-seccion">
    <div class="sitio-contenedor">
        <h2>Los más vendidos</h2>
        <p class="sitio-seccion-intro">Lo que más piden nuestros clientes.</p>
        <div class="tarjetas-grid">
            <?php foreach ($masVendidos as $p): $tarjetaProducto($p); endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== DESTACADOS ===================== -->
<?php if ($destacados !== []): ?>
<section class="sitio-seccion sitio-seccion-alt">
    <div class="sitio-contenedor">
        <h2>Destacados</h2>
        <p class="sitio-seccion-intro">Nuestras recomendaciones.</p>
        <div class="tarjetas-grid">
            <?php foreach ($destacados as $p): $tarjetaProducto($p); endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== TEMPORADA ===================== -->
<?php /*
    Antes esta sección dependía de $productosTemporada: una temporada vigente
    sin ningún producto todavía etiquetado (Productos → pestaña Temporadas) no
    mostraba nada — ni su nombre, ni su foto — como si no existiera
    (docs/arquitectura.md §4.19). Ahora se basa en $temporadas (las vigentes
    en sí, siempre que existan) y los productos, si los hay, van debajo.
*/ ?>
<?php if ($temporadas !== []): ?>
<section class="sitio-seccion" id="temporada">
    <div class="sitio-contenedor">
        <h2>De temporada</h2>
        <?php foreach ($temporadas as $t): ?>
            <article class="tarjeta-producto tarjeta-temporada">
                <?php if (!empty($t['imagen'])): ?>
                    <img src="<?= UPLOADS_URL ?>/temporadas/<?= htmlspecialchars($t['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt=""
                         loading="lazy" decoding="async">
                <?php endif; ?>
                <div class="tarjeta-producto-cuerpo">
                    <p class="tarjeta-producto-nombre"><?= htmlspecialchars($t['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($t['descripcion'])): ?>
                        <p class="tarjeta-producto-descripcion"><?= htmlspecialchars($t['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="tarjeta-producto-descripcion">Válido hasta <?= htmlspecialchars(Vista::fechaLarga($t['fecha_fin']), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if ($productosTemporada !== []): ?>
            <div class="tarjetas-grid" style="margin-top:20px;">
                <?php foreach ($productosTemporada as $p): $tarjetaProducto($p); endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===================== PROMOCIONES VIGENTES ===================== -->
<?php /*
    Solo promociones activas y dentro de su rango de fechas (y, si aplican,
    día de la semana / horario) — Promocion::listarVigentesAhora() ya lo
    calcula todo en el servidor (docs/arquitectura.md §4.18). Sin ninguna
    vigente ahora mismo, la sección entera no se imprime: nada de espacio
    vacío ni mensaje de "no hay promociones".
*/ ?>
<?php if ($promociones !== []): ?>
<section class="sitio-seccion sitio-seccion-alt" id="promociones">
    <div class="sitio-contenedor">
        <h2>Promociones</h2>
        <p class="sitio-seccion-intro">Vigentes en este momento.</p>
        <div class="tarjetas-grid">
            <?php foreach ($promociones as $promo): ?>
                <?php
                    // Vinculada a lealtad: la tarjeta entera es un enlace a /cuenta, donde
                    // se reclama el cupón real (§4.20). Sin sesión, ClienteAuthMiddleware
                    // manda primero a iniciar sesión o crear cuenta, y de ahí ya cae en
                    // /cuenta — nunca hace falta volver a hacer clic en la promoción.
                    $esDeLealtad = (int) ($promo['combinable_con_lealtad'] ?? 0) === 1;
                    $etiquetaHtml = $esDeLealtad ? 'a' : 'article';
                ?>
                <<?= $etiquetaHtml ?> class="tarjeta-producto<?= $esDeLealtad ? ' tarjeta-producto-enlace' : '' ?>"
                    <?= $esDeLealtad ? 'href="/cuenta"' : '' ?>>
                    <?php if (!empty($promo['imagen'])): ?>
                        <img src="<?= UPLOADS_URL ?>/promociones/<?= htmlspecialchars($promo['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt=""
                             loading="lazy" decoding="async">
                    <?php endif; ?>
                    <div class="tarjeta-producto-cuerpo">
                        <p class="tarjeta-producto-nombre"><?= htmlspecialchars($promo['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (($etiqueta = Vista::etiquetaPromocion($promo)) !== ''): ?>
                            <div class="etiquetas-producto">
                                <span class="etiqueta etiqueta-promocion"><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($esDeLealtad): ?><span class="etiqueta etiqueta-destacado">Programa de lealtad</span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($promo['descripcion'])): ?>
                            <p class="tarjeta-producto-descripcion"><?= htmlspecialchars($promo['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p class="tarjeta-producto-descripcion">
                            <?= htmlspecialchars($promo['producto_nombre'] ?? $promo['categoria_nombre'] ?? 'Todo el menú', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="tarjeta-producto-descripcion">Válido hasta <?= htmlspecialchars(Vista::fechaLarga($promo['fecha_fin']), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($esDeLealtad): ?>
                            <p class="tarjeta-producto-descripcion" style="color:var(--color-verde);font-weight:700;">Reclama tu cupón en Mi cuenta →</p>
                        <?php endif; ?>
                    </div>
                </<?= $etiquetaHtml ?>>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== MENÚ ===================== -->
<section class="sitio-seccion" id="menu">
    <div class="sitio-contenedor">
        <h2>Nuestro menú</h2>

        <?php if ($menu === []): ?>
            <p class="sitio-seccion-intro">El menú se está preparando.</p>
        <?php else: ?>
            <?php /* En móvil sitio.js la vuelve una barra fija con pestañas: al tocar una baja hasta esa
                     categoría y, al desplazarse, marca la que se está viendo. Sin JavaScript, o en
                     escritorio, son enlaces de ancla normales. */ ?>
            <nav class="menu-nav" id="menu-nav" aria-label="Ir a una categoría" data-menu-pestanas>
                <?php foreach ($menu as $cat): ?>
                    <a href="#cat-<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($menu as $cat): ?>
                <div class="menu-categoria" id="cat-<?= htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <h3><?= htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if (!empty($cat['notas'])): ?>
                        <p class="menu-categoria-notas"><?= htmlspecialchars($cat['notas'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="tarjetas-grid">
                        <?php foreach ($cat['productos'] as $p): $tarjetaProducto($p); endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== SOBRE NOSOTROS ===================== -->
<section class="sitio-seccion sitio-seccion-alt" id="nosotros">
    <div class="sitio-contenedor">
        <h2>Sobre nosotros</h2>
        <div class="sobre-nosotros-contenido">
            <?php if (!empty($config['sobre_nosotros_texto'])): ?>
                <p><?= nl2br(htmlspecialchars($config['sobre_nosotros_texto'], ENT_QUOTES, 'UTF-8')) ?></p>
            <?php else: ?>
                <p><?= Vista::pendiente('texto de "Sobre nosotros"') ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===================== GALERÍA ===================== -->
<section class="sitio-seccion" id="galeria">
    <div class="sitio-contenedor">
        <h2>Galería</h2>
        <?php if ($galeria === []): ?>
            <p class="sitio-seccion-intro"><?= Vista::pendiente('fotografías de la galería') ?></p>
        <?php else: ?>
            <?php /*
                Carrusel: la "pista" es un contenedor con scroll horizontal y
                scroll-snap, así que ya se puede deslizar con el dedo o la
                rueda SIN JavaScript. sitio.js solo agrega flechas, puntos y
                avance automático encima de eso.
            */ ?>
            <div class="carrusel" data-carrusel role="region" aria-roledescription="carrusel" aria-label="Galería de fotos">
                <div class="carrusel-pista" tabindex="0">
                    <?php foreach ($galeria as $indice => $foto): ?>
                        <?php
                        $textoAlt = $foto['titulo'] ?: ($foto['descripcion'] ?: $nombreSitio . ' — foto ' . ($indice + 1));
                        ?>
                        <figure class="carrusel-item">
                            <img src="<?= htmlspecialchars(UPLOADS_URL . '/galeria/' . $foto['imagen'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($textoAlt, ENT_QUOTES, 'UTF-8') ?>"
                                 loading="lazy" decoding="async">
                            <?php if (!empty($foto['titulo'])): ?>
                                <figcaption><?= htmlspecialchars($foto['titulo'], ENT_QUOTES, 'UTF-8') ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="carrusel-boton carrusel-anterior" aria-label="Foto anterior">‹</button>
                <button type="button" class="carrusel-boton carrusel-siguiente" aria-label="Foto siguiente">›</button>
                <div class="carrusel-puntos" role="group" aria-label="Elegir foto"></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== UBICACIÓN / MAPA / CALIFICACIÓN ===================== -->
<section class="sitio-seccion sitio-seccion-alt" id="ubicacion">
    <div class="sitio-contenedor">
        <h2>Ubicación</h2>
        <div class="ubicacion-grid">
            <div>
                <?php $direccionTexto = $config['direccion_texto'] ?? ''; ?>
                <?php if ($direccionTexto !== ''): ?>
                    <?php
                    // El mapa ya no depende de que el Administrador pegue un código
                    // embed (propenso a errores: un link corto de compartir ahí no
                    // sirve, como pasó antes). Se genera solo a partir de la
                    // dirección de texto, con el formato de Google Maps sin API key.
                    $urlMapa = 'https://www.google.com/maps?q=' . rawurlencode($direccionTexto) . '&output=embed';
                    ?>
                    <div class="ubicacion-mapa-marcador">
                        <iframe src="<?= htmlspecialchars($urlMapa, ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                title="Mapa de ubicación de <?= htmlspecialchars($nombreSitio, ENT_QUOTES, 'UTF-8') ?>"></iframe>
                    </div>
                <?php else: ?>
                    <div class="ubicacion-mapa-marcador"><?= Vista::pendiente('mapa de Google Maps') ?></div>
                <?php endif; ?>
                <p style="margin-top:12px;">
                    <?= $direccionTexto !== '' ? htmlspecialchars($direccionTexto, ENT_QUOTES, 'UTF-8') : Vista::pendiente('dirección exacta') ?>
                </p>
                <?php
                // Este campo (google_maps_embed) ahora guarda el enlace de
                // compartir de Google Maps (https://maps.app.goo.gl/...), no
                // código embed: se usa para que el cliente trace su propia ruta.
                $enlaceComoLlegar = $config['google_maps_embed'] ?? '';
                ?>
                <?php if ($enlaceComoLlegar !== ''): ?>
                    <a class="btn btn-como-llegar" href="<?= htmlspecialchars($enlaceComoLlegar, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        Cómo llegar
                    </a>
                <?php endif; ?>
            </div>
            <div>
                <h3>Calificación en Google</h3>
                <?php if (!empty($config['google_calificacion'])): ?>
                    <?php $urlResena = $config['google_calificacion_url'] ?? ''; ?>
                    <?php if ($urlResena !== ''): ?>
                        <a class="calificacion-google" href="<?= htmlspecialchars($urlResena, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                            ⭐ <?= htmlspecialchars($config['google_calificacion'], ENT_QUOTES, 'UTF-8') ?> en Google
                        </a>
                    <?php else: ?>
                        <span class="calificacion-google">⭐ <?= htmlspecialchars($config['google_calificacion'], ENT_QUOTES, 'UTF-8') ?> en Google</span>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?= Vista::pendiente('calificación de Google') ?></p>
                <?php endif; ?>

                <h3 style="margin-top:24px;">Horario</h3>
                <?php
                    $hayHorarioAbierto = false;
                    foreach ($horarios as $h) {
                        if ((int) $h['cerrado'] === 0) { $hayHorarioAbierto = true; break; }
                    }
                ?>
                <?php if (!$hayHorarioAbierto): ?>
                    <p><?= Vista::pendiente('horario de atención') ?></p>
                <?php else: ?>
                    <p>Consulta el horario completo al pie de la página.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===================== LEALTAD ===================== -->
<section class="sitio-seccion" id="lealtad">
    <div class="sitio-contenedor">
        <h2>Programa de lealtad</h2>
        <div class="lealtad-tarjeta">
            <div class="lealtad-numero"><?= (int) $umbralLealtad ?></div>
            <p>
                Compra <?= (int) $umbralLealtad ?> cafés y el siguiente es <strong>gratis</strong> —
                cualquier café de nuestro menú. Si te faltan algunos para completar el ciclo,
                no se pierden: se acumulan para el siguiente.
            </p>
            <p>Pregunta en el mostrador para empezar a acumular.</p>
            <a href="/cuenta" class="btn btn-verde">Ver mi progreso</a>
            <p class="campo-ayuda" style="margin-top:8px;">
                ¿No tienes cuenta? <a href="/cuenta/registro">Créala con tu teléfono y un PIN</a> — es gratis y solo toma un minuto.
            </p>
        </div>
    </div>
</section>

<?php
require APP_PATH . '/Views/layouts/footer_publico.php';
