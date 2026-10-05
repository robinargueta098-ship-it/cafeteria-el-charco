<?php
/**
 * Carrito de compras del sitio público (sin pasarela de pago): botón flotante con contador y
 * panel con el pedido. Al confirmar se abre WhatsApp con el pedido ya escrito, hacia el número
 * configurado. Toda la lógica vive en /assets/js/carrito.js.
 *
 * @var array<string,string> $config
 * @var array<int,array<string,mixed>> $horarios
 */
use App\Core\Vista;
use App\Core\WhatsApp;
use App\Models\HorarioAtencion;

$numeroCarrito = preg_replace('/[^0-9]/', '', (string) ($config['whatsapp_numero'] ?? '')) ?? '';
$nombreCarrito = (string) ($config['nombre_sitio'] ?? 'Cafetería El Charco');

// El horario se lee de la base en cada carga (Configuración → Horario), no está escrito en el código.
$datosCarrito = [
    'whatsapp' => $numeroCarrito,
    'sitio'    => $nombreCarrito,
    'zona'     => APP_TIMEZONE,
    'horario'  => HorarioAtencion::paraCarrito($horarios),
];
$avisoUbicacion = \App\Models\Configuracion::avisoUbicacion($config);
$iconoCarrito = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H6"/></svg>';
?>

<div id="carrito" class="carrito" hidden
     data-carrito="<?= htmlspecialchars((string) json_encode($datosCarrito, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>">
    <div class="carrito-fondo" id="carrito-fondo" hidden></div>

    <aside class="carrito-panel" id="carrito-panel" role="dialog" aria-modal="true" aria-labelledby="carrito-titulo" hidden>
        <header class="carrito-encabezado">
            <h2 id="carrito-titulo">Tu pedido</h2>
            <button type="button" class="carrito-cerrar" id="carrito-cerrar" aria-label="Cerrar el carrito">&times;</button>
        </header>

        <div class="carrito-cuerpo">
            <div class="carrito-aviso" id="carrito-aviso" role="status" hidden></div>
            <p class="carrito-vacio" id="carrito-vacio">Tu carrito está vacío. Agrega productos del menú con el botón <strong>+ Agregar</strong>.</p>
            <ul class="carrito-lista" id="carrito-lista"></ul>

            <div class="carrito-formulario" id="carrito-formulario" hidden>
            <fieldset class="carrito-datos">
                <legend>Tus datos</legend>
                <div class="carrito-campo">
                    <label for="carrito-nombre">Nombre <span class="carrito-obligatorio" aria-hidden="true">*</span></label>
                    <input type="text" id="carrito-nombre" name="cliente_nombre" maxlength="60" autocomplete="name" required
                           placeholder="¿A nombre de quién va el pedido?">
                </div>
                <div class="carrito-campo">
                    <label for="carrito-direccion">Dirección <span class="carrito-obligatorio" aria-hidden="true">*</span></label>
                    <input type="text" id="carrito-direccion" name="cliente_direccion" maxlength="200" autocomplete="street-address" required
                           placeholder="Calle, número, zona y alguna referencia">
                </div>
                <div class="carrito-campo">
                    <label for="carrito-detalles">Detalles extra <span class="carrito-opcional">(opcional)</span></label>
                    <textarea id="carrito-detalles" name="cliente_detalles" rows="2" maxlength="300"
                              placeholder="Ej. sin azúcar, leche deslactosada, timbre descompuesto…"></textarea>
                </div>
            </fieldset>

            <fieldset class="carrito-pago">
                <legend>Forma de pago</legend>
                <?php foreach (['Pago contra entrega', 'Transferencia', 'Tarjeta física'] as $i => $forma): ?>
                    <label class="carrito-opcion">
                        <input type="radio" name="forma_pago" value="<?= htmlspecialchars($forma, ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= htmlspecialchars($forma, ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                <?php endforeach; ?>
                <p class="carrito-ayuda">Solo para que sepamos cómo vas a pagar; aquí no se cobra nada. La tarjeta física se paga en persona al recoger.</p>
            </fieldset>
            </div>
        </div>

        <footer class="carrito-pie" id="carrito-pie" hidden>
            <div class="carrito-total"><span>Total</span><strong id="carrito-total">Q0.00</strong></div>

            <p class="carrito-error" id="carrito-error" role="alert" hidden></p>

            <?php if ($numeroCarrito !== ''): ?>
                <div class="carrito-ubicacion" id="carrito-ubicacion">
                    <p class="carrito-ubicacion-texto"><span aria-hidden="true">&#128205;</span> <?= htmlspecialchars($avisoUbicacion, ENT_QUOTES, 'UTF-8') ?></p>
                    <label class="carrito-entendido" for="carrito-entendido">
                        <input type="checkbox" id="carrito-entendido" name="entendido_ubicacion">
                        <span>Entendido</span>
                    </label>
                </div>
                <a href="#" class="btn btn-whatsapp carrito-enviar" id="carrito-enviar" target="_blank" rel="noopener">
                    <?= Vista::iconoWhatsapp() ?>
                    <span>Enviar pedido por WhatsApp</span>
                </a>
            <?php else: ?>
                <p class="carrito-error">El número de WhatsApp del negocio todavía no está configurado.</p>
            <?php endif; ?>

            <button type="button" class="carrito-vaciar" id="carrito-vaciar">Vaciar carrito</button>
        </footer>

        <?php /* Después de tocar "Enviar": se pregunta si el mensaje realmente salió (el sitio no puede saberlo). */ ?>
        <section class="carrito-estado" id="carrito-confirmar" hidden aria-live="polite">
            <h3>¿Ya enviaste tu pedido?</h3>
            <p>Se abrió WhatsApp con tu pedido listo. Cuando lo hayas enviado, avísanos aquí para vaciar el carrito.</p>
            <button type="button" class="btn carrito-estado-boton" id="carrito-ya-envie">Sí, ya lo envié</button>
            <button type="button" class="btn btn-secundario carrito-estado-boton" id="carrito-volver">No, volver a mi pedido</button>
        </section>

        <section class="carrito-estado" id="carrito-gracias" hidden aria-live="polite">
            <div class="carrito-gracias-icono" aria-hidden="true">&#10003;</div>
            <h3>¡Gracias por tu pedido!</h3>
            <p>Ya lo tenemos en WhatsApp. Te responderemos por ahí para confirmártelo. Guardamos tu nombre y dirección para tu próximo pedido.</p>
            <button type="button" class="btn carrito-estado-boton" id="carrito-cerrar-gracias">Cerrar</button>
        </section>
    </aside>
</div>

<script src="<?= htmlspecialchars(Vista::asset('/assets/js/carrito.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
