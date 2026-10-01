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
    nombre: document.getElementById('carrito-nombre'),
    direccion: document.getElementById('carrito-direccion'),
    detalles: document.getElementById('carrito-detalles'),
    vaciar: document.getElementById('carrito-vaciar')
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
      precio: parseFloat(boton.getAttribute('data-precio')) || 0, corta: boton.getAttribute('data-corta') || ''
    }];
    if (claveAlt) {
      versiones.push({
        clave: claveAlt, nombre: boton.getAttribute('data-alt-nombre'),
        precio: parseFloat(boton.getAttribute('data-alt-precio')) || 0, corta: boton.getAttribute('data-alt-corta') || ''
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
    } catch (e) { /* sin almacenamiento: el carrito vive solo en esta visita */ }
  }

  function guardar() {
    try {
      window.localStorage.setItem(CLAVE_ALMACEN, JSON.stringify({ items: items, orden: orden, pago: pago, cliente: cliente }));
    } catch (e) { /* ignorar */ }
  }

  // ---- Utilidades ----
  function dinero(n) {
    return 'Q' + n.toFixed(2);
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
    var proximo = hoy.turnos.filter(function (turno) { return turno[0] > t.minutos; })[0];
    return {
      abierto: abierto,
      hoy: hoy.texto,
      proxima: proximo ? 'Abrimos hoy a las ' + hhmm(proximo[0]) + '.' : ''
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
    detalle.textContent = ' ' + [hoy, estado.proxima].filter(Boolean).join(' ') + ' Lo atenderemos en cuanto abramos.';
    el.aviso.appendChild(detalle);
  }

  // ---- Mensaje de WhatsApp ----
  function limpiar(texto) {
    return String(texto).replace(/[*_~`]/g, '');   // estos caracteres son formato en WhatsApp
  }

  function construirMensaje() {
    var lineas = ['*Pedido - ' + limpiar(datos.sitio) + '*', '', '*Detalle del pedido:*'];
    claves().forEach(function (c) {
      var p = catalogo[c];
      var cantidad = items[c];
      lineas.push('- ' + cantidad + ' x ' + limpiar(p.nombre) + ' - ' + dinero(p.precio) + ' c/u = ' + dinero(cantidad * p.precio));
    });
    lineas.push('');
    lineas.push('*Total: ' + dinero(totalPedido()) + '*');
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
    return lineas.join('\n');
  }

  function enlaceWhatsapp() {
    return 'https://wa.me/' + datos.whatsapp + '?text=' + encodeURIComponent(construirMensaje());
  }

  // ---- Datos obligatorios (nombre, dirección y forma de pago; los detalles son opcionales) ----
  function datosFaltantes() {
    var falta = [];
    if (cliente.nombre.trim() === '') { falta.push('nombre'); }
    if (cliente.direccion.trim() === '') { falta.push('direccion'); }
    if (!pago) { falta.push('pago'); }
    return falta;
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
    el.pie.hidden = !hayItems;
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
    if (el.enviar) {
      el.enviar.href = hayItems && falta.length === 0 ? enlaceWhatsapp() : '#';
      el.enviar.setAttribute('aria-disabled', hayItems && falta.length === 0 ? 'false' : 'true');
    }
    pintarAviso();
  }

  function cambiar(clave, delta) {
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

  function abrirPanel() {
    ultimoFoco = document.activeElement;
    pintar();
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
      var listo = totalArticulos() > 0 && falta.length === 0;
      if (el.enviar) {
        el.enviar.href = listo ? enlaceWhatsapp() : '#';
        el.enviar.setAttribute('aria-disabled', listo ? 'false' : 'true');
      }
      if (falta.length === 0) { el.error.hidden = true; }
      if (par[0] !== 'detalles' && par[1].value.trim() !== '') { par[1].setAttribute('aria-invalid', 'false'); }
    });
  });

  el.vaciar.addEventListener('click', function () {
    items = {};
    orden = [];
    cliente.detalles = '';
    guardar();
    pintar();
  });

  if (el.enviar) {
    el.enviar.addEventListener('click', function (evento) {
      if (totalArticulos() === 0) {
        evento.preventDefault();
        return;
      }
      var falta = datosFaltantes();
      if (falta.length > 0) {
        // Faltan datos obligatorios. Esto NO es el horario: el horario nunca bloquea el envío.
        evento.preventDefault();
        el.error.textContent = mensajeFaltante(falta);
        el.error.hidden = false;
        marcarInvalidos(falta);
        var primero = falta[0] === 'nombre' ? el.nombre : (falta[0] === 'direccion' ? el.direccion : null);
        if (primero) { primero.focus(); }
        return;
      }
      el.enviar.href = enlaceWhatsapp();   // mensaje con la hora de este instante; el enlace sigue su curso
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
