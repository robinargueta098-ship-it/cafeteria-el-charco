/*
 * Carrito del sitio público. Sin pasarela de pago: al confirmar se abre WhatsApp con el pedido
 * ya escrito (número configurado en el panel). Datos que vienen del servidor, nunca escritos aquí:
 *  - productos, nombres completos y precios: de los botones [data-agregar] del menú;
 *  - número de WhatsApp, nombre del sitio, zona horaria y horario de atención: de data-carrito.
 * El horario es SOLO informativo: fuera de horario se avisa, pero el envío nunca se bloquea.
 */
(function () {
  'use strict';

  var raiz = document.getElementById('carrito');
  if (!raiz) {
    return;
  }

  var datos;
  try {
    datos = JSON.parse(raiz.getAttribute('data-carrito') || '{}');
  } catch (e) {
    return;
  }
  raiz.hidden = false;
  var botonCarrito = document.getElementById('carrito-abrir');
  if (!botonCarrito) {
    return;
  }
  botonCarrito.hidden = false;   // el botón del encabezado solo se muestra si el carrito funciona

  var CLAVE_ALMACEN = 'elcharco_carrito_v1';

  var el = {
    abrir: document.getElementById('carrito-abrir'),
    cerrar: document.getElementById('carrito-cerrar'),
    fondo: document.getElementById('carrito-fondo'),
    panel: document.getElementById('carrito-panel'),
    contador: document.getElementById('carrito-contador'),
    aviso: document.getElementById('carrito-aviso'),
    vacio: document.getElementById('carrito-vacio'),
    lista: document.getElementById('carrito-lista'),
    pie: document.getElementById('carrito-pie'),
    formulario: document.getElementById('carrito-formulario'),
    total: document.getElementById('carrito-total'),
    error: document.getElementById('carrito-error'),
    enviar: document.getElementById('carrito-enviar'),
    entendido: document.getElementById('carrito-entendido'),
    nombre: document.getElementById('carrito-nombre'),
    direccion: document.getElementById('carrito-direccion'),
    detalles: document.getElementById('carrito-detalles'),
    vaciar: document.getElementById('carrito-vaciar'),
    cuerpo: document.querySelector('.carrito-cuerpo'),
    confirmar: document.getElementById('carrito-confirmar'),
    gracias: document.getElementById('carrito-gracias'),
    yaEnvie: document.getElementById('carrito-ya-envie'),
    volver: document.getElementById('carrito-volver'),
    cerrarGracias: document.getElementById('carrito-cerrar-gracias')
  };

  // ---- Catálogo: lo que hay en la página (precio y nombre siempre actuales) ----
  var catalogo = {};
  var NOMBRES_TEMP = { c: 'Caliente', f: 'Frío' };

  // Cada combinación pedible (versión x temperatura) es una entrada con su propia clave:
  //   "12"  producto sin opciones · "12-alt" segunda versión (Batido) · "12~c" / "12~f" caliente / frío
  Array.prototype.forEach.call(document.querySelectorAll('[data-agregar]'), function (boton) {
    var base = boton.getAttribute('data-clave');
    var claveAlt = boton.getAttribute('data-alt-clave');
    var temp = boton.getAttribute('data-temp') || '';
    var temps = temp === 'ambas' ? ['c', 'f'] : (temp === 'caliente' ? ['c'] : (temp === 'frio' ? ['f'] : ['']));
    var versiones = [{
      clave: base, nombre: boton.getAttribute('data-nombre'),
      precio: aCentavos(boton.getAttribute('data-precio')), corta: boton.getAttribute('data-corta') || ''
    }];
    if (claveAlt) {
      versiones.push({
        clave: claveAlt, nombre: boton.getAttribute('data-alt-nombre'),
        precio: aCentavos(boton.getAttribute('data-alt-precio')), corta: boton.getAttribute('data-alt-corta') || ''
      });
    }
    versiones.forEach(function (v, i) {
      temps.forEach(function (t) {
        var sufijo = t ? '~' + t : '';
        var otra = versiones[1 - i];
        catalogo[v.clave + sufijo] = {
          nombre: v.nombre + (t ? ' - ' + NOMBRES_TEMP[t] : ''),
          precio: v.precio,
          corta: v.corta,
          hermana: otra ? otra.clave + sufijo : '',                       // la otra versión (Frappé <-> Batido)
          otraTemp: temp === 'ambas' ? v.clave + '~' + (t === 'c' ? 'f' : 'c') : '',   // la otra temperatura
          tempCorta: t ? NOMBRES_TEMP[t] : ''
        };
      });
    });
  });

  // ---- Estado (se guarda en el navegador; si no se puede, funciona igual sin guardar) ----
  var items = {};     // clave -> cantidad
  var orden = [];     // claves en el orden en que se agregaron (así se ve y así sale en el mensaje)
  var pago = '';
  var cliente = { nombre: '', direccion: '', detalles: '' };
  var modoActual = 'pedido';   // 'pedido' | 'confirmar' | 'gracias'
  var entendido = false;   // casilla «Entendido» del aviso de ubicación: no se guarda, hay que marcarla en cada pedido
  var enviado = false;     // se tocó "Enviar" y falta saber si el mensaje realmente salió
  var tokenPedido = '';    // identifica ESTE pedido ante el servidor: reintentos y doble clic no crean otro
  var ultimoPedido = null; // respuesta del servidor del pedido ya guardado (código, ítems y total reales)
  var enviando = false;    // hay una petición en curso (el botón queda deshabilitado)
  var ultimaActividad = 0; // para vaciar un carrito olvidado
  var CADUCA_MS = 12 * 60 * 60 * 1000;   // 12 horas sin tocarlo

  function leer() {
    try {
      var guardado = JSON.parse(window.localStorage.getItem(CLAVE_ALMACEN) || '{}');
      var guardadas = Array.isArray(guardado.orden) ? guardado.orden : Object.keys(guardado.items || {});
      guardadas.forEach(function (clave) {
        var cantidad = parseInt((guardado.items || {})[clave], 10);
        if (catalogo[clave] && cantidad > 0 && !items.hasOwnProperty(clave)) {   // un producto que ya no está en el menú se descarta
          items[clave] = Math.min(99, cantidad);
          orden.push(clave);
        }
      });
      pago = typeof guardado.pago === 'string' ? guardado.pago : '';
      var c = guardado.cliente || {};
      cliente.nombre = typeof c.nombre === 'string' ? c.nombre : '';
      cliente.direccion = typeof c.direccion === 'string' ? c.direccion : '';
      cliente.detalles = typeof c.detalles === 'string' ? c.detalles : '';
      enviado = guardado.enviado === true;
      tokenPedido = typeof guardado.token === 'string' && /^[A-Za-z0-9_-]{16,64}$/.test(guardado.token) ? guardado.token : '';
      ultimoPedido = enviado && guardado.pedido && typeof guardado.pedido.codigo === 'string' ? guardado.pedido : null;
      ultimaActividad = parseInt(guardado.ultimaActividad, 10) || 0;

      // Un carrito olvidado por más de 12 horas se vacía solo (se conservan nombre y dirección).
      if (ultimaActividad > 0 && Date.now() - ultimaActividad > CADUCA_MS) {
        items = {};
        orden = [];
        pago = '';
        cliente.detalles = '';
        enviado = false;
        tokenPedido = '';
        ultimoPedido = null;
      }
    } catch (e) { /* sin almacenamiento: el carrito vive solo en esta visita */ }
  }

  function guardar() {
    try {
      window.localStorage.setItem(CLAVE_ALMACEN, JSON.stringify({ items: items, orden: orden, pago: pago, cliente: cliente, enviado: enviado, token: tokenPedido, pedido: ultimoPedido, ultimaActividad: Date.now() }));
    } catch (e) { /* ignorar */ }
  }

  // ---- Utilidades ----
  // DINERO: siempre en CENTAVOS ENTEROS (nunca decimales de punto flotante). "38.36" -> 3836.
  function aCentavos(texto) {
    var m = /^(\d+)(?:\.(\d+))?$/.exec(String(texto === null || texto === undefined ? '' : texto).trim());
    if (!m) { return 0; }
    var dec = (m[2] || '') + '000';
    var c = parseInt(m[1], 10) * 100 + parseInt(dec.substr(0, 2), 10);
    if (parseInt(dec.charAt(2), 10) >= 5) { c += 1; }   // redondeo half-up, igual que el servidor
    return c;
  }

  // 123450 -> "Q1,234.50"  (mismo formato que el servidor: App\Core\Dinero::formato)
  function dinero(centavos) {
    var c = Math.round(centavos);
    var signo = c < 0 ? '-' : '';
    if (c < 0) { c = -c; }
    var enteros = String(Math.floor(c / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    var cents = c % 100;
    return signo + 'Q' + enteros + '.' + (cents < 10 ? '0' : '') + cents;
  }

  function claves() {
    return orden.filter(function (c) { return items.hasOwnProperty(c); });
  }

  function totalArticulos() {
    return claves().reduce(function (suma, c) { return suma + items[c]; }, 0);
  }

  function totalPedido() {
    return claves().reduce(function (suma, c) { return suma + items[c] * catalogo[c].precio; }, 0);
  }

  // ---- Horario de atención (dinámico, desde Configuración) ----
  function ahora() {
    return window.__elcharcoAhora instanceof Date ? window.__elcharcoAhora : new Date();   // __elcharcoAhora: solo para pruebas
  }

  // Día de la semana (0 = domingo) y minutos desde medianoche EN LA ZONA HORARIA DEL NEGOCIO,
  // no la del teléfono del cliente.
  function enZonaDelNegocio(fecha) {
    var dia = fecha.getDay();
    var minutos = fecha.getHours() * 60 + fecha.getMinutes();
    try {
      var partes = new Intl.DateTimeFormat('en-US', {
        timeZone: datos.zona, weekday: 'short', hour: 'numeric', minute: 'numeric', hourCycle: 'h23'
      }).formatToParts(fecha);
      var mapa = {};
      partes.forEach(function (p) { mapa[p.type] = p.value; });
      var dias = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
      if (dias.hasOwnProperty(mapa.weekday)) {
        dia = dias[mapa.weekday];
        minutos = (parseInt(mapa.hour, 10) % 24) * 60 + parseInt(mapa.minute, 10);
      }
    } catch (e) { /* navegador sin Intl con zonas: se usa la hora local */ }
    return { dia: dia, minutos: minutos };
  }

  function hhmm(minutos) {
    var h = Math.floor(minutos / 60);
    var m = minutos % 60;
    return h + ':' + (m < 10 ? '0' : '') + m;
  }

  var NOMBRES_DIA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

  /**
   * Próxima vez que abre el negocio, mirando el resto de hoy y luego los días siguientes según el
   * horario configurado en el panel (incluye el segundo turno de un horario partido).
   * @return {string} ej. "hoy a las 16:00", "mañana (sábado) a las 17:00", "el lunes a las 9:00"; '' si nunca abre
   */
  function proximaApertura(horario, t) {
    for (var desplazamiento = 0; desplazamiento <= 7; desplazamiento++) {
      var dia = (t.dia + desplazamiento) % 7;
      var datosDia = horario.filter(function (d) { return d.dia === dia; })[0];
      if (!datosDia) {
        continue;
      }
      var turno = datosDia.turnos.filter(function (tr) { return desplazamiento > 0 || tr[0] > t.minutos; })[0];
      if (turno) {
        var cuando = desplazamiento === 0 ? 'hoy'
          : (desplazamiento === 1 ? 'mañana (' + NOMBRES_DIA[dia] + ')' : 'el ' + NOMBRES_DIA[dia]);
        return cuando + ' a las ' + hhmm(turno[0]);
      }
    }
    return '';
  }

  /** @return {{abierto:boolean, hoy:string, proxima:string}} */
  function estadoHorario() {
    var horario = datos.horario || [];
    if (horario.length === 0) {
      return { abierto: true, hoy: '', proxima: '' };   // sin horario configurado: no hay nada que avisar
    }
    var t = enZonaDelNegocio(ahora());
    var hoy = horario.filter(function (d) { return d.dia === t.dia; })[0];
    if (!hoy) {
      return { abierto: true, hoy: '', proxima: '' };
    }
    var abierto = hoy.turnos.some(function (turno) { return t.minutos >= turno[0] && t.minutos < turno[1]; });
    var proxima = abierto ? '' : proximaApertura(horario, t);
    return {
      abierto: abierto,
      hoy: hoy.texto,
      proxima: proxima ? 'Volvemos a atender ' + proxima + '.' : ''
    };
  }

  function pintarAviso() {
    var estado = estadoHorario();
    if (estado.abierto) {
      el.aviso.hidden = true;
      return;
    }
    el.aviso.hidden = false;
    el.aviso.textContent = '';
    var principal = document.createElement('strong');
    principal.textContent = 'En este momento no estamos atendiendo, pero tu pedido sí se puede enviar.';
    el.aviso.appendChild(principal);
    var detalle = document.createElement('span');
    var hoy = estado.hoy === 'Cerrado' ? 'Hoy no abrimos.' : (estado.hoy ? 'Horario de hoy: ' + estado.hoy + '.' : '');
    detalle.textContent = ' ' + [estado.proxima, hoy].filter(Boolean).join(' ') + ' Lo atenderemos en cuanto abramos.';
    el.aviso.appendChild(detalle);
  }

  // ---- Mensaje de WhatsApp ----
  function limpiar(texto) {
    return String(texto).replace(/[*_~`]/g, '');   // estos caracteres son formato en WhatsApp
  }

  // Con "pedido" (la respuesta del servidor al guardarlo) el mensaje lleva EL CÓDIGO y los precios, subtotales y
  // total que calculó y guardó el servidor: son exactamente los de la base de datos. Sin él (pruebas/soporte) usa el carrito.
  function construirMensaje(pedido) {
    var lineas = ['*Pedido - ' + limpiar(datos.sitio) + '*'];
    if (pedido && pedido.codigo) {
      lineas.push('Pedido *#' + pedido.codigo + '*');
    }
    lineas.push('', '*Detalle del pedido:*');
    if (pedido && pedido.items) {
      pedido.items.forEach(function (it) {
        lineas.push('- ' + it.cantidad + ' x ' + limpiar(it.nombre) + ' - ' + it.precio + ' c/u = ' + it.subtotal);
      });
    } else {
      claves().forEach(function (c) {
        var p = catalogo[c];
        var cantidad = items[c];
        lineas.push('- ' + cantidad + ' x ' + limpiar(p.nombre) + ' - ' + dinero(p.precio) + ' c/u = ' + dinero(cantidad * p.precio));
      });
    }
    lineas.push('');
    lineas.push('*Total: ' + (pedido && pedido.total ? pedido.total : dinero(totalPedido())) + '*');
    lineas.push('*Forma de pago: ' + limpiar(pago) + '*');
    lineas.push('');
    lineas.push('*Nombre:* ' + limpiar(cliente.nombre.trim()));
    lineas.push('*Direccion:* ' + limpiar(cliente.direccion.trim()));
    if (cliente.detalles.trim() !== '') {
      lineas.push('*Detalles extra:* ' + limpiar(cliente.detalles.trim()).replace(/\s*\n\s*/g, ' '));
    }
    if (!estadoHorario().abierto) {
      lineas.push('');
      lineas.push('_Pedido enviado fuera del horario de atencion._');
    }
    lineas.push('');
    lineas.push('Te enviaré mi ubicación a continuación.');
    return lineas.join('\n');
  }

  // En teléfono, wa.me abre directo la aplicación. En computadora, wa.me pasa por una página de WhatsApp que lanza
  // sola la aplicación de escritorio (si está instalada) antes de elegir: ahí se usa WhatsApp Web directamente.
  function esTelefono() {
    var ua = navigator.userAgent || '';
    return /Android|iPhone|iPad|iPod|Mobile|Windows Phone/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  }

  function enlaceWhatsapp(pedido) {
    var texto = encodeURIComponent(construirMensaje(pedido));
    if (esTelefono()) {
      return 'https://wa.me/' + datos.whatsapp + '?text=' + texto;
    }
    return 'https://web.whatsapp.com/send?phone=' + datos.whatsapp + '&text=' + texto;
  }

  // Identificador de este envío (UUID-like). Se conserva mientras no cambie el pedido: reintentos y doble clic = un solo pedido.
  function nuevoToken() {
    var bytes = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
    } else {
      for (var i = 0; i < 16; i++) { bytes[i] = Math.floor(Math.random() * 256); }
    }
    return Array.prototype.map.call(bytes, function (b) { return (b < 16 ? '0' : '') + b.toString(16); }).join('');
  }

  // ---- Datos obligatorios (nombre, dirección y forma de pago; los detalles son opcionales) ----
  function datosFaltantes() {
    var falta = [];
    if (cliente.nombre.trim() === '') { falta.push('nombre'); }
    if (cliente.direccion.trim() === '') { falta.push('direccion'); }
    if (!pago) { falta.push('pago'); }
    return falta;
  }

  // El botón de WhatsApp solo se habilita con carrito, datos completos y la casilla «Entendido» marcada.
  // (El horario NO interviene aquí: es solo informativo y nunca bloquea el envío.)
  function sincronizarEnviar() {
    if (!el.enviar) { return; }
    var listo = totalArticulos() > 0 && datosFaltantes().length === 0 && entendido;
    el.enviar.href = '#';   // el clic guarda el pedido y luego abre WhatsApp (ver enviarPedido)
    el.enviar.setAttribute('aria-disabled', listo ? 'false' : 'true');
    el.enviar.classList.toggle('carrito-enviar--bloqueado', !entendido);
  }

  function reiniciarEntendido() {
    entendido = false;
    if (el.entendido) { el.entendido.checked = false; }
  }

  function marcarInvalidos(falta) {
    el.nombre.setAttribute('aria-invalid', falta.indexOf('nombre') !== -1 ? 'true' : 'false');
    el.direccion.setAttribute('aria-invalid', falta.indexOf('direccion') !== -1 ? 'true' : 'false');
    raiz.querySelector('.carrito-pago').classList.toggle('carrito-pago--falta', falta.indexOf('pago') !== -1);
  }

  function mensajeFaltante(falta) {
    var nombres = { nombre: 'tu nombre', direccion: 'tu dirección', pago: 'la forma de pago' };
    var lista = falta.map(function (f) { return nombres[f]; });
    var texto = lista.length > 1 ? lista.slice(0, -1).join(', ') + ' y ' + lista[lista.length - 1] : lista[0];
    return 'Para enviar tu pedido falta: ' + texto + '.';
  }

  // ---- Pintar ----
  function pintar() {
    var articulos = totalArticulos();
    el.contador.textContent = articulos > 99 ? '99+' : String(articulos);
    el.contador.hidden = articulos === 0;
    el.abrir.setAttribute('aria-label', 'Ver mi carrito' + (articulos ? ' (' + articulos + ' artículo' + (articulos === 1 ? '' : 's') + ')' : ''));

    el.lista.textContent = '';
    claves().forEach(function (clave) {
      var p = catalogo[clave];
      var li = document.createElement('li');
      li.className = 'carrito-item';
      li.setAttribute('data-clave', clave);

      var info = document.createElement('div');
      info.className = 'carrito-item-info';
      var nombre = document.createElement('p');
      nombre.className = 'carrito-item-nombre';
      nombre.textContent = p.nombre;
      var unitario = document.createElement('p');
      unitario.className = 'carrito-item-unitario';
      unitario.textContent = dinero(p.precio) + ' c/u';
      info.appendChild(nombre);
      info.appendChild(unitario);

      function selector(opciones, etiqueta) {
        var grupo = document.createElement('div');
        grupo.className = 'carrito-versiones';
        grupo.setAttribute('role', 'group');
        grupo.setAttribute('aria-label', etiqueta + ' de ' + p.nombre);
        opciones.forEach(function (o) {
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = o.texto;
          b.setAttribute('data-accion', 'version');
          b.setAttribute('data-destino', o.clave);
          b.setAttribute('aria-pressed', o.clave === clave ? 'true' : 'false');
          grupo.appendChild(b);
        });
        return grupo;
      }

      // Producto con dos versiones: se elige aquí cuál se pide (el mensaje de WhatsApp lleva el nombre completo).
      if (p.hermana && catalogo[p.hermana]) {
        var claves2 = [clave, p.hermana].sort(function (x, y) { return (x.indexOf('-alt') === -1 ? 0 : 1) - (y.indexOf('-alt') === -1 ? 0 : 1); });
        info.appendChild(selector(claves2.map(function (k) {
          return { clave: k, texto: catalogo[k].corta + ' \u00b7 ' + dinero(catalogo[k].precio) };
        }), 'Versión'));
      }

      // Producto que se puede pedir caliente o frío: se puede cambiar aquí.
      if (p.otraTemp && catalogo[p.otraTemp]) {
        var temps2 = [clave, p.otraTemp].sort(function (x, y) { return (catalogo[x].tempCorta === 'Caliente' ? 0 : 1) - (catalogo[y].tempCorta === 'Caliente' ? 0 : 1); });
        info.appendChild(selector(temps2.map(function (k) {
          return { clave: k, texto: catalogo[k].tempCorta };
        }), 'Temperatura'));
      }

      var control = document.createElement('div');
      control.className = 'carrito-cantidad';
      function boton(texto, accion, etiqueta) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = texto;
        b.setAttribute('data-accion', accion);
        b.setAttribute('aria-label', etiqueta + ': ' + p.nombre);
        return b;
      }
      var valor = document.createElement('span');
      valor.className = 'carrito-cantidad-valor';
      valor.textContent = String(items[clave]);
      control.appendChild(boton('−', 'menos', 'Quitar uno'));
      control.appendChild(valor);
      control.appendChild(boton('+', 'mas', 'Agregar uno'));

      var subtotal = document.createElement('p');
      subtotal.className = 'carrito-item-subtotal';
      subtotal.textContent = dinero(items[clave] * p.precio);

      var quitar = boton('×', 'quitar', 'Quitar del carrito');
      quitar.className = 'carrito-item-quitar';

      li.appendChild(info);
      li.appendChild(control);
      li.appendChild(subtotal);
      li.appendChild(quitar);
      el.lista.appendChild(li);
    });

    var hayItems = articulos > 0;
    el.vacio.hidden = hayItems;
    el.pie.hidden = !hayItems || modoActual !== 'pedido';
    el.formulario.hidden = !hayItems;
    el.total.textContent = dinero(totalPedido());

    Array.prototype.forEach.call(raiz.querySelectorAll('input[name="forma_pago"]'), function (radio) {
      radio.checked = radio.value === pago;
    });
    if (document.activeElement !== el.nombre) { el.nombre.value = cliente.nombre; }
    if (document.activeElement !== el.direccion) { el.direccion.value = cliente.direccion; }
    if (document.activeElement !== el.detalles) { el.detalles.value = cliente.detalles; }

    var falta = datosFaltantes();
    if (falta.length === 0) {
      el.error.hidden = true;
      marcarInvalidos([]);
    }
    if (el.entendido) { el.entendido.checked = entendido; }
    sincronizarEnviar();
    pintarAviso();
  }

  function cambiar(clave, delta) {
    enviado = false;
    var nueva = (items[clave] || 0) + delta;
    if (nueva <= 0) {
      delete items[clave];
      orden = orden.filter(function (c) { return c !== clave; });
    } else {
      if (!items.hasOwnProperty(clave)) {
        orden.push(clave);
      }
      items[clave] = Math.min(99, nueva);
    }
    guardar();
    pintar();
  }

  // Cambia una línea a la otra versión del producto (Frappé <-> Batido), conservando la cantidad.
  function cambiarVersion(clave, destino) {
    if (!items.hasOwnProperty(clave) || !catalogo[destino] || destino === clave) {
      return;
    }
    var cantidad = items[clave];
    if (items.hasOwnProperty(destino)) {
      items[destino] = Math.min(99, items[destino] + cantidad);       // ya había de esa versión: se suman
      orden = orden.filter(function (c) { return c !== clave; });
    } else {
      items[destino] = cantidad;
      orden = orden.map(function (c) { return c === clave ? destino : c; });   // mismo lugar en la lista
    }
    delete items[clave];
    guardar();
    pintar();
  }

  // ---- Panel ----
  var ultimoFoco = null;

  // Qué se ve dentro del panel: el pedido, la pregunta "¿ya lo enviaste?" o el agradecimiento.
  function mostrarModo(modo) {
    modoActual = modo;
    var pedido = modo === 'pedido';
    el.cuerpo.hidden = !pedido;
    el.confirmar.hidden = modo !== 'confirmar';
    el.gracias.hidden = modo !== 'gracias';
    if (!pedido) {
      el.pie.hidden = true;
    } else {
      el.pie.hidden = totalArticulos() === 0;
    }
  }

  function modoInicial() {
    return enviado && totalArticulos() > 0 ? 'confirmar' : 'pedido';
  }

  function abrirPanel() {
    ultimoFoco = document.activeElement;
    pintar();
    mostrarModo(modoInicial());
    el.fondo.hidden = false;
    el.panel.hidden = false;
    document.documentElement.classList.add('carrito-abierto');
    el.cerrar.focus();
  }

  function cerrarPanel() {
    el.fondo.hidden = true;
    el.panel.hidden = true;
    document.documentElement.classList.remove('carrito-abierto');
    if (ultimoFoco && ultimoFoco.focus) {
      ultimoFoco.focus();
    }
  }

  // ---- Eventos ----
  function cantidadDeTarjeta(boton) {
    var salida = boton.parentNode.querySelector('.cantidad-agregar output');
    return Math.max(1, Math.min(99, parseInt(salida ? salida.textContent : '1', 10) || 1));
  }

  document.addEventListener('click', function (evento) {
    var objetivo = evento.target.closest ? evento.target : null;
    if (!objetivo) {
      return;
    }

    // - / + de la tarjeta: cuántos se van a agregar
    var paso = objetivo.closest('.cantidad-agregar button[data-cantidad]');
    if (paso) {
      var salida = paso.parentNode.querySelector('output');
      var nueva = Math.max(1, Math.min(99, (parseInt(salida.textContent, 10) || 1) + parseInt(paso.getAttribute('data-cantidad'), 10)));
      salida.textContent = String(nueva);
      return;
    }

    // Caliente / Frío de la tarjeta
    var chip = objetivo.closest('.temperatura-agregar button[data-temperatura]');
    if (chip) {
      Array.prototype.forEach.call(chip.parentNode.querySelectorAll('button'), function (otro) {
        otro.setAttribute('aria-pressed', otro === chip ? 'true' : 'false');
      });
      var aviso = chip.closest('.tarjeta-producto').querySelector('.temperatura-aviso');
      if (aviso) { aviso.hidden = true; }
      return;
    }

    var boton = objetivo.closest('[data-agregar]');
    if (!boton) {
      return;
    }
    var clave = boton.getAttribute('data-clave');
    if (boton.getAttribute('data-temp') === 'ambas') {
      var tarjeta = boton.closest('.tarjeta-producto');
      var elegida = tarjeta.querySelector('.temperatura-agregar [aria-pressed="true"]');
      if (!elegida) {
        // Falta elegir caliente o frío (es necesario para saber qué preparar).
        var mensajeTemp = tarjeta.querySelector('.temperatura-aviso');
        if (mensajeTemp) { mensajeTemp.hidden = false; }
        tarjeta.querySelector('.temperatura-agregar').classList.add('temperatura-agregar--falta');
        return;
      }
      tarjeta.querySelector('.temperatura-agregar').classList.remove('temperatura-agregar--falta');
      clave += '~' + elegida.getAttribute('data-temperatura');
    } else if (boton.getAttribute('data-temp') === 'caliente') {
      clave += '~c';
    } else if (boton.getAttribute('data-temp') === 'frio') {
      clave += '~f';
    }
    var cuantos = cantidadDeTarjeta(boton);
    cambiar(clave, cuantos);
    var salidaTarjeta = boton.parentNode.querySelector('.cantidad-agregar output');
    if (salidaTarjeta) {
      salidaTarjeta.textContent = '1';
    }

    el.abrir.classList.remove('nav-carrito--salto');
    void el.abrir.offsetWidth;   // reinicia la animación
    el.abrir.classList.add('nav-carrito--salto');

    var original = boton.getAttribute('data-texto') || boton.textContent;
    boton.setAttribute('data-texto', original);
    boton.textContent = cuantos > 1 ? cuantos + ' agregados \u2713' : 'Agregado \u2713';
    boton.classList.add('btn-agregar--listo');
    window.setTimeout(function () {
      boton.textContent = original;
      boton.classList.remove('btn-agregar--listo');
    }, 1000);
  });

  el.abrir.addEventListener('click', abrirPanel);
  el.cerrar.addEventListener('click', cerrarPanel);
  el.fondo.addEventListener('click', cerrarPanel);
  document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape' && !el.panel.hidden) {
      cerrarPanel();
    }
  });

  el.lista.addEventListener('click', function (evento) {
    var boton = evento.target.closest('button[data-accion]');
    if (!boton) {
      return;
    }
    var clave = boton.closest('li').getAttribute('data-clave');
    var accion = boton.getAttribute('data-accion');
    if (accion === 'version') {
      cambiarVersion(clave, boton.getAttribute('data-destino'));
      return;
    }
    cambiar(clave, accion === 'mas' ? 1 : (accion === 'menos' ? -1 : -items[clave]));
  });

  Array.prototype.forEach.call(raiz.querySelectorAll('input[name="forma_pago"]'), function (radio) {
    radio.addEventListener('change', function () {
      pago = radio.value;
      guardar();
      pintar();
    });
  });

  [['nombre', el.nombre], ['direccion', el.direccion], ['detalles', el.detalles]].forEach(function (par) {
    par[1].addEventListener('input', function () {
      cliente[par[0]] = par[1].value;
      guardar();
      var falta = datosFaltantes();
      sincronizarEnviar();
      if (falta.length === 0) { el.error.hidden = true; }
      if (par[0] !== 'detalles' && par[1].value.trim() !== '') { par[1].setAttribute('aria-invalid', 'false'); }
    });
  });

  if (el.entendido) {
    el.entendido.addEventListener('change', function () {
      entendido = el.entendido.checked;
      if (entendido && datosFaltantes().length === 0) { el.error.hidden = true; }
      sincronizarEnviar();
    });
  }

  el.yaEnvie.addEventListener('click', function () {
    // El pedido estaba «pendiente» en el servidor; ahora el cliente confirma que sí envió el mensaje.
    if (tokenPedido) {
      var cuerpo = new URLSearchParams();
      cuerpo.set('csrf_token', datos.csrf || '');
      cuerpo.set('token', tokenPedido);
      try {
        fetch((datos.pedidoUrl || '/pedido') + '/confirmar', { method: 'POST', body: cuerpo, credentials: 'same-origin', keepalive: true })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j && j.error === 'sesion') {   // token viejo: se renueva y se confirma otra vez
              renovarCsrf().then(function (ok) {
                if (!ok) { return; }
                var otra = new URLSearchParams();
                otra.set('csrf_token', datos.csrf);
                otra.set('token', cuerpo.get('token'));
                fetch((datos.pedidoUrl || '/pedido') + '/confirmar', { method: 'POST', body: otra, credentials: 'same-origin', keepalive: true });
              });
            }
          }, function () { /* sin conexión: el pedido sigue pendiente */ });
      } catch (e) { /* si falla, el pedido sigue pendiente y el personal lo puede confirmar con el código de WhatsApp */ }
    }
    reiniciarEntendido();
    tokenPedido = '';
    ultimoPedido = null;
    items = {};
    orden = [];
    pago = '';
    cliente.detalles = '';      // nombre y dirección se conservan para el próximo pedido
    enviado = false;
    guardar();
    pintar();
    mostrarModo('gracias');
    el.cerrarGracias.focus();
  });

  el.volver.addEventListener('click', function () {
    enviado = false;
    guardar();
    pintar();
    mostrarModo('pedido');
  });

  el.cerrarGracias.addEventListener('click', cerrarPanel);

  // Si el cliente regresa a esta pestaña después de ir a WhatsApp, se le pregunta de inmediato.
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && enviado && totalArticulos() > 0) {
      if (el.panel.hidden) {
        abrirPanel();
      } else {
        mostrarModo('confirmar');
      }
    }
  });

  el.vaciar.addEventListener('click', function () {
    reiniciarEntendido();
    tokenPedido = '';
    ultimoPedido = null;
    items = {};
    orden = [];
    cliente.detalles = '';
    guardar();
    pintar();
  });

  function mostrarErrorEnvio(texto) {
    el.error.textContent = texto;
    el.error.hidden = false;
  }

  function cerrarVentana(ventana) {
    try { if (ventana && !ventana.closed) { ventana.close(); } } catch (e) { /* ignorar */ }
  }

  function terminarEnvio() {
    enviando = false;
    el.enviar.removeAttribute('aria-busy');
    el.enviar.classList.remove('carrito-enviar--enviando');
    var rotulo = el.enviar.querySelector('span');
    if (rotulo) { rotulo.textContent = 'Enviar pedido por WhatsApp'; }
  }

  // Guarda el pedido en el servidor y SOLO si quedó guardado abre WhatsApp, con el código y los totales reales.
  function renovarCsrf() {
    return fetch((datos.pedidoUrl || '/pedido') + '/token', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && typeof j.csrf === 'string' && j.csrf) { datos.csrf = j.csrf; return true; }
        return false;
      }, function () { return false; });
  }

  function enviarPedido(ventana, esReintento) {
    if (enviando) { return; }
    enviando = true;
    el.enviar.setAttribute('aria-busy', 'true');
    el.enviar.classList.add('carrito-enviar--enviando');
    var rotulo = el.enviar.querySelector('span');
    if (rotulo) { rotulo.textContent = 'Guardando tu pedido…'; }

    if (!tokenPedido) {
      tokenPedido = nuevoToken();
      guardar();
    }

    var cuerpo = new URLSearchParams();
    cuerpo.set('csrf_token', datos.csrf || '');
    cuerpo.set('token', tokenPedido);
    cuerpo.set('items', JSON.stringify(claves().map(function (c) { return { clave: c, cantidad: items[c] }; })));
    cuerpo.set('forma_pago', pago);
    cuerpo.set('nombre', cliente.nombre.trim());
    cuerpo.set('direccion', cliente.direccion.trim());
    cuerpo.set('detalles', cliente.detalles.trim());
    cuerpo.set('total_cliente', String(totalPedido()));

    fetch(datos.pedidoUrl || '/pedido', {
      method: 'POST',
      body: cuerpo,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (respuesta) {
      return respuesta.json().then(function (json) { return json; }, function () { return { ok: false, error: 'respuesta' }; });
    }).then(function (resp) {
      terminarEnvio();

      if (resp && resp.ok) {
        ultimoPedido = { codigo: resp.codigo, items: resp.items, total: resp.total, total_centavos: resp.total_centavos };
        enviado = true;
        guardar();
        var url = enlaceWhatsapp(ultimoPedido);
        if (ventana && !ventana.closed) {
          ventana.location.href = url;
        } else {
          window.location.href = url;   // el navegador bloqueó la pestaña nueva: se abre en esta
        }
        reiniciarEntendido();
        sincronizarEnviar();
        mostrarModo('confirmar');
        return;
      }

      // La sesión cambió desde que se abrió la página (p. ej. se inició sesión en el panel): se pide un token
      // nuevo y se reintenta una vez, sin que el cliente tenga que recargar.
      if (resp && resp.error === 'sesion' && !esReintento) {
        renovarCsrf().then(function (ok) {
          if (ok) { enviarPedido(ventana, true); return; }
          cerrarVentana(ventana);
          mostrarErrorEnvio(resp.mensaje || 'La página estaba desactualizada. Recárgala y vuelve a enviar tu pedido.');
        });
        return;
      }

      // El mismo identificador con otro carrito (el cliente cambió algo después de un intento): se genera uno nuevo y se reintenta una vez.
      if (resp && resp.error === 'token_reutilizado' && !esReintento) {
        tokenPedido = nuevoToken();
        guardar();
        enviarPedido(ventana, true);
        return;
      }

      cerrarVentana(ventana);

      if (resp && resp.error === 'precios_cambiaron' && resp.precios) {
        Object.keys(resp.precios).forEach(function (clave) {
          if (catalogo[clave]) { catalogo[clave].precio = resp.precios[clave]; }
        });
        tokenPedido = '';
        guardar();
        pintar();
        mostrarErrorEnvio(resp.mensaje || 'Algunos precios cambiaron. Revisa tu pedido y vuelve a enviarlo.');
        return;
      }

      if (resp && resp.error === 'no_disponible' && resp.claves) {
        resp.claves.forEach(function (clave) {
          delete items[clave];
          orden = orden.filter(function (c) { return c !== clave; });
        });
        tokenPedido = '';
        guardar();
        pintar();
        mostrarErrorEnvio(resp.mensaje || 'Algunos productos ya no están disponibles y se quitaron de tu pedido.');
        return;
      }

      mostrarErrorEnvio((resp && resp.mensaje) || 'No pudimos guardar tu pedido. Tu carrito sigue aquí; inténtalo de nuevo en un momento.');
    }).catch(function () {
      terminarEnvio();
      cerrarVentana(ventana);
      mostrarErrorEnvio('No pudimos guardar tu pedido. Revisa tu conexión: tu carrito sigue aquí; inténtalo de nuevo.');
    });
  }

  if (el.enviar) {
    el.enviar.addEventListener('click', function (evento) {
      evento.preventDefault();   // WhatsApp se abre desde enviarPedido(), una vez guardado el pedido

      if (enviando || totalArticulos() === 0) {
        return;
      }
      var falta = datosFaltantes();
      if (falta.length > 0) {
        // Faltan datos obligatorios. Esto NO es el horario: el horario nunca bloquea el envío.
        el.error.textContent = mensajeFaltante(falta);
        el.error.hidden = false;
        marcarInvalidos(falta);
        var primero = falta[0] === 'nombre' ? el.nombre : (falta[0] === 'direccion' ? el.direccion : null);
        if (primero) { primero.focus(); }
        return;
      }
      if (!entendido) {
        el.error.textContent = 'Marca «Entendido» para poder enviar tu pedido.';
        el.error.hidden = false;
        if (el.entendido) { el.entendido.focus(); }
        return;
      }

      el.error.hidden = true;
      // Se abre ya una pestaña en blanco (dentro del clic, para que el navegador no la bloquee); se llena al guardar.
      var ventana = null;
      try { ventana = window.open('', '_blank'); } catch (e) { ventana = null; }
      enviarPedido(ventana, false);
    });
  }

  // El aviso de horario se mantiene al día mientras el carrito está abierto.
  window.setInterval(function () {
    if (!el.panel.hidden) {
      pintarAviso();
    }
  }, 30000);

  // Para pruebas y soporte: ver el mensaje exacto sin abrir WhatsApp.
  window.elcharcoCarrito = { mensaje: construirMensaje, estado: estadoHorario, items: function () { return JSON.parse(JSON.stringify(items)); } };

  leer();
  pintar();
})();
