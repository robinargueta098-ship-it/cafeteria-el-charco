/**
 * JavaScript del panel administrativo. Solo mejoras de interfaz
 * (mostrar/ocultar campos según una selección) — toda la validación
 * real ocurre en el servidor (App\Core\Validator y cada controlador),
 * esto es puramente para que el formulario sea más claro de llenar.
 */
/**
 * Rellena el formulario con el borrador que dejó el servidor (window.__BORRADOR) cuando un envío se rechazó
 * por token vencido o sesión expirada. Solo toca el formulario cuyo action coincide con el envío rechazado.
 */
function restaurarBorrador() {
  var borrador = window.__BORRADOR;
  if (!borrador || !borrador.datos) {
    return;
  }

  var forms = Array.prototype.filter.call(document.querySelectorAll('form'), function (f) {
    var destino = f.getAttribute('action');
    var ruta = destino ? new URL(destino, window.location.href).pathname : window.location.pathname;
    return ruta.replace(/\/$/, '') === borrador.ruta.replace(/\/$/, '');
  });

  forms.forEach(function (form) {
    Array.prototype.forEach.call(form.elements, function (campo) {
      var nombre = campo.name;
      if (!nombre || campo.type === 'file' || campo.type === 'hidden' || campo.type === 'submit' || campo.type === 'button') {
        return;
      }
      var clave = nombre.replace(/\[\]$/, '');
      var guardado = borrador.datos[clave];

      if (campo.type === 'checkbox' || campo.type === 'radio') {
        var lista = Array.isArray(guardado) ? guardado : (guardado === undefined ? [] : [guardado]);
        campo.checked = lista.indexOf(campo.value) !== -1;
      } else if (guardado !== undefined && !Array.isArray(guardado)) {
        campo.value = guardado;
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  restaurarBorrador();
  inicializarSobreNosotros();   // después de restaurar el borrador: así toma los valores ya recuperados
  // Campos de teléfono: solo números (y como máximo los que permite maxlength) mientras se escribe o se pega.
  document.querySelectorAll('input[data-solo-digitos]').forEach(function (campo) {
    campo.addEventListener('input', function () {
      var limpio = campo.value.replace(/\D/g, '');
      if (limpio !== campo.value) {
        campo.value = limpio;
      }
    });
  });

  inicializarAlcancePromocion();
  inicializarTipoPromocion();
  document.querySelectorAll('input[data-subida-multiple]').forEach(inicializarSubidaMultiple);
  inicializarMenuPanel();
  inicializarPistaTablas();
});

/**
 * Menú lateral en celular: se abre y se cierra con el botón hamburguesa de
 * la barra superior (en escritorio ese botón no se ve y el menú siempre
 * está a la vista). Se cierra también con Escape, tocando el fondo oscuro,
 * al elegir una sección y al pasar a un ancho de escritorio. Mientras está
 * abierto, Tab no se escapa hacia el contenido de atrás.
 */
function inicializarMenuPanel() {
  var boton = document.querySelector('.panel-menu-boton');
  var lateral = document.getElementById('panel-sidebar');
  if (!boton || !lateral) {
    return;
  }

  var fondo = document.querySelector('[data-panel-cerrar-menu]');
  var esMovil = window.matchMedia('(max-width: 767.98px)');

  function estaAbierto() {
    return lateral.classList.contains('abierto');
  }

  function abrir() {
    lateral.classList.add('abierto');
    document.body.classList.add('menu-abierto');
    boton.setAttribute('aria-expanded', 'true');
    boton.setAttribute('aria-label', 'Cerrar menú');

    var primerEnlace = lateral.querySelector('a');
    if (primerEnlace) {
      primerEnlace.focus();
    }
  }

  function cerrar(devolverFoco) {
    lateral.classList.remove('abierto');
    document.body.classList.remove('menu-abierto');
    boton.setAttribute('aria-expanded', 'false');
    boton.setAttribute('aria-label', 'Abrir menú');

    if (devolverFoco) {
      boton.focus();
    }
  }

  boton.addEventListener('click', function () {
    if (estaAbierto()) {
      cerrar(true);
    } else {
      abrir();
    }
  });

  if (fondo) {
    fondo.addEventListener('click', function () { cerrar(true); });
  }

  lateral.querySelectorAll('a').forEach(function (enlace) {
    enlace.addEventListener('click', function () { cerrar(false); });
  });

  document.addEventListener('keydown', function (evento) {
    if (!estaAbierto()) {
      return;
    }

    if (evento.key === 'Escape') {
      cerrar(true);
      return;
    }

    if (evento.key === 'Tab') {
      // El ciclo de foco incluye el menú y el propio botón (que sigue visible arriba).
      var enfocables = Array.prototype.slice.call(lateral.querySelectorAll('a[href], button:not([disabled])'));
      enfocables.push(boton);
      var primero = enfocables[0];
      var ultimo = enfocables[enfocables.length - 1];

      if (evento.shiftKey && document.activeElement === primero) {
        evento.preventDefault();
        ultimo.focus();
      } else if (!evento.shiftKey && document.activeElement === ultimo) {
        evento.preventDefault();
        primero.focus();
      }
    }
  });

  function alCambiarAncho(evento) {
    if (!evento.matches) {
      cerrar(false); // se giró a horizontal / se agrandó la ventana: el menú vuelve a ser fijo
    }
  }

  if (esMovil.addEventListener) {
    esMovil.addEventListener('change', alCambiarAncho);
  } else if (esMovil.addListener) {
    esMovil.addListener(alCambiarAncho); // Safari antiguo
  }
}

/**
 * En celular, las tablas que no caben se deslizan hacia los lados dentro de
 * su tarjeta; esto agrega arriba un aviso corto para que se note (solo si de
 * verdad desbordan, y solo en pantalla chica).
 */
function inicializarPistaTablas() {
  var contenedores = Array.prototype.slice.call(document.querySelectorAll('.tabla-contenedor'));
  if (contenedores.length === 0) {
    return;
  }

  var esMovil = window.matchMedia('(max-width: 767.98px)');

  function actualizar() {
    contenedores.forEach(function (contenedor) {
      if (!contenedor.querySelector('table.tabla:not(.tabla-apilada)')) {
        return; // las listas de fotos se apilan, no se deslizan
      }

      var anterior = contenedor.previousElementSibling;
      var pista = anterior && anterior.classList.contains('tabla-pista') ? anterior : null;
      var desborda = esMovil.matches && contenedor.scrollWidth > contenedor.clientWidth + 2;

      if (desborda && !pista) {
        pista = document.createElement('p');
        pista.className = 'tabla-pista';
        pista.textContent = 'Desliza la tabla hacia los lados para ver más columnas ↔';
        contenedor.parentNode.insertBefore(pista, contenedor);
      } else if (!desborda && pista) {
        pista.parentNode.removeChild(pista);
      }
    });
  }

  window.addEventListener('resize', actualizar);
  actualizar();
}

/**
 * Subida de varias fotos (galería y portada): avisa de inmediato si alguna
 * es muy pesada, tiene un formato no permitido o son demasiadas, en vez de
 * esperar a que el servidor rechace todo el envío. Es solo comodidad — el
 * servidor valida cada archivo de nuevo (tipo real, tamaño, cupo) y es
 * quien decide.
 *
 * El input indica dónde escribir el resumen y qué botón bloquear con
 * data-resumen / data-boton (ids), y sus límites con data-max-archivos y
 * data-max-bytes.
 */
function inicializarSubidaMultiple(input) {
  var resumen = document.getElementById(input.getAttribute('data-resumen'));
  var boton = document.getElementById(input.getAttribute('data-boton'));
  if (!resumen || !boton) {
    return;
  }

  var maxArchivos = parseInt(input.getAttribute('data-max-archivos'), 10);
  var maxBytes = parseInt(input.getAttribute('data-max-bytes'), 10);
  var extensionesOk = /\.(jpe?g|png|webp)$/i;

  function mb(bytes) {
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  input.addEventListener('change', function () {
    var archivos = Array.prototype.slice.call(input.files);
    var problemas = [];

    if (archivos.length > maxArchivos) {
      problemas.push('Elegiste ' + archivos.length + ' fotos; el máximo por envío es ' + maxArchivos + '.');
    }

    archivos.forEach(function (archivo) {
      if (!extensionesOk.test(archivo.name)) {
        problemas.push(archivo.name + ': formato no permitido (solo JPG, PNG o WEBP).');
      } else if (archivo.size > maxBytes) {
        problemas.push(archivo.name + ': pesa ' + mb(archivo.size) + ' (máximo ' + mb(maxBytes) + ').');
      }
    });

    resumen.textContent = '';

    if (problemas.length > 0) {
      var lista = document.createElement('ul');
      lista.style.color = 'var(--color-error)';
      problemas.forEach(function (texto) {
        var item = document.createElement('li');
        item.textContent = texto; // textContent: el nombre del archivo nunca se interpreta como HTML
        lista.appendChild(item);
      });
      resumen.appendChild(lista);
      boton.disabled = true;
      return;
    }

    boton.disabled = false;
    if (archivos.length > 0) {
      var total = archivos.reduce(function (suma, a) { return suma + a.size; }, 0);
      resumen.textContent = archivos.length + ' foto(s) seleccionada(s), ' + mb(total) + ' en total.';
    }
  });
}

function inicializarAlcancePromocion() {
  var radios = document.querySelectorAll('input[name="alcance"]');
  if (radios.length === 0) {
    return;
  }

  var grupoCategoria = document.getElementById('grupo-categoria');
  var grupoProducto = document.getElementById('grupo-producto');

  function actualizar() {
    var seleccionado = document.querySelector('input[name="alcance"]:checked');
    var valor = seleccionado ? seleccionado.value : 'todo';

    if (grupoCategoria) grupoCategoria.hidden = valor !== 'categoria';
    if (grupoProducto) grupoProducto.hidden = valor !== 'producto';
  }

  radios.forEach(function (radio) {
    radio.addEventListener('change', actualizar);
  });

  actualizar();
}

function inicializarTipoPromocion() {
  var select = document.getElementById('tipo');
  var grupoValor = document.getElementById('grupo-valor');
  if (!select || !grupoValor) {
    return;
  }

  var tiposConValor = ['descuento_porcentaje', 'descuento_monto'];

  function actualizar() {
    grupoValor.hidden = tiposConValor.indexOf(select.value) === -1;
  }

  select.addEventListener('change', actualizar);
  actualizar();
}


/**
 * Configuración → "Sobre nosotros": tarjetas editables (agregar, quitar, subir/bajar) y vista previa en vivo.
 * Los campos son planos (nosotros_titulo_1…6 / nosotros_desc_1…6) y siempre van en orden desde la 1:
 * al agregar, quitar o reordenar, los valores se mueven entre los lugares; no se renombra nada. La vista previa
 * usa las mismas clases que el sitio (sobre-nosotros.css) y arma todo con textContent: nunca interpreta HTML.
 */
function inicializarSobreNosotros() {
  var contenedor = document.querySelector('[data-nosotros-tarjetas]');
  var previa = document.querySelector('[data-nosotros-previa]');
  if (!contenedor || !previa) {
    return;
  }

  var MAX = parseInt(contenedor.getAttribute('data-max'), 10) || 6;
  var lugares = Array.prototype.slice.call(contenedor.querySelectorAll('[data-nosotros-tarjeta]'));
  var botonAgregar = contenedor.querySelector('[data-nosotros-agregar]');
  var contador = contenedor.querySelector('[data-nosotros-contador]');
  var form = contenedor.closest('form');
  var intro = document.getElementById('nosotros_intro');
  var subtitulo = document.getElementById('nosotros_subtitulo');
  var cierre = document.getElementById('nosotros_cierre');
  var foto = document.getElementById('sobre_nosotros_foto');
  var quitarFoto = document.getElementById('quitar_sobre_nosotros_foto');
  var urlLocal = null;

  function campos(i) {
    return { t: lugares[i].querySelector('input[type="text"]'), d: lugares[i].querySelector('textarea') };
  }

  function leer(i) {
    var c = campos(i);
    return { titulo: c.t.value, descripcion: c.d.value };
  }

  function escribir(i, valor) {
    var c = campos(i);
    c.t.value = valor.titulo;
    c.d.value = valor.descripcion;
  }

  function usado(i) {
    var v = leer(i);
    return v.titulo.trim() !== '' || v.descripcion.trim() !== '';
  }

  function visibles() {
    return lugares.filter(function (l) { return !l.hidden; }).length;
  }

  // Los lugares visibles son siempre los primeros N; los de más abajo quedan ocultos y vacíos.
  function mostrarHasta(n) {
    lugares.forEach(function (l, i) { l.hidden = i >= n; });
  }

  function refrescar() {
    var n = visibles();
    lugares.forEach(function (l, i) {
      l.querySelector('[data-nosotros-numero]').textContent = String(i + 1);
      l.querySelector('[data-nosotros-accion="subir"]').disabled = i === 0;
      l.querySelector('[data-nosotros-accion="bajar"]').disabled = i === n - 1;
    });
    botonAgregar.hidden = n >= MAX;
    contador.textContent = '(' + lugares.filter(function (_, i) { return i < n && usado(i); }).length + ' de ' + MAX + ')';
    pintarPrevia();
  }

  // Al abrir: junta las tarjetas con contenido desde el primer lugar (por si un borrador dejó huecos).
  var llenas = [];
  lugares.forEach(function (_, i) { if (usado(i)) { llenas.push(leer(i)); } });
  lugares.forEach(function (_, i) { escribir(i, llenas[i] || { titulo: '', descripcion: '' }); });
  mostrarHasta(llenas.length);

  contenedor.addEventListener('click', function (evento) {
    var boton = evento.target.closest('[data-nosotros-accion]');
    if (!boton) { return; }
    var lugar = boton.closest('[data-nosotros-tarjeta]');
    var i = lugares.indexOf(lugar);
    var n = visibles();
    var accion = boton.getAttribute('data-nosotros-accion');

    if (accion === 'quitar') {
      for (var j = i; j < n - 1; j++) { escribir(j, leer(j + 1)); }
      escribir(n - 1, { titulo: '', descripcion: '' });
      mostrarHasta(n - 1);
    } else {
      var otro = accion === 'subir' ? i - 1 : i + 1;
      if (otro < 0 || otro >= n) { return; }
      var a = leer(i);
      escribir(i, leer(otro));
      escribir(otro, a);
      campos(otro).t.focus();
    }
    refrescar();
  });

  botonAgregar.addEventListener('click', function () {
    var n = visibles();
    if (n >= MAX) { return; }
    mostrarHasta(n + 1);
    refrescar();
    campos(n).t.focus();
  });

  // Foto: avisar de inmediato si pesa de más, y mostrarla en la vista previa antes de guardar.
  if (foto) {
    foto.addEventListener('change', function () {
      foto.setCustomValidity('');
      if (urlLocal) { URL.revokeObjectURL(urlLocal); urlLocal = null; }
      var archivo = foto.files && foto.files[0];
      var maximo = parseInt(foto.getAttribute('data-max-bytes'), 10) || 0;
      if (archivo && maximo && archivo.size > maximo) {
        foto.setCustomValidity('La imagen pesa más de ' + Math.round(maximo / 1048576) + ' MB. Elige una más liviana.');
        foto.reportValidity();
        foto.value = '';
      } else if (archivo) {
        urlLocal = URL.createObjectURL(archivo);
      }
      pintarPrevia();
    });
  }

  function elemento(etiqueta, clase, texto) {
    var e = document.createElement(etiqueta);
    e.className = clase;
    if (texto !== undefined) { e.textContent = texto; }
    return e;
  }

  function conDosPuntos(texto) {
    return /:$/.test(texto) ? texto : texto + ':';
  }

  function pintarPrevia() {
    previa.textContent = '';

    var partesIntro = intro.value.replace(/\r\n?/g, '\n').split(/\n[ \t\u200b]*\n/).map(function (p) {
      return p.split('\n').map(function (l) { return l.trim(); }).filter(function (l) { return l !== ''; });
    }).filter(function (lineas) { return lineas.length > 0; });
    var textoSub = subtitulo.value.trim();
    var tarjetas = [];
    lugares.forEach(function (_, i) {
      var v = leer(i);
      if (!lugares[i].hidden && v.titulo.trim() !== '') { tarjetas.push({ titulo: v.titulo.trim(), descripcion: v.descripcion.trim() }); }
    });
    var textoCierre = cierre.value.trim();

    var urlFoto = '';
    if (urlLocal) {
      urlFoto = urlLocal;
    } else if (quitarFoto && quitarFoto.checked) {
      urlFoto = previa.getAttribute('data-foto-galeria') || '';
    } else {
      urlFoto = previa.getAttribute('data-foto-actual') || previa.getAttribute('data-foto-galeria') || '';
    }

    if (partesIntro.length === 0 && textoSub === '' && tarjetas.length === 0 && textoCierre === '') {
      previa.classList.remove('sobre-nosotros--con-foto');
      previa.appendChild(elemento('p', 'sobre-nosotros-previa-vacia', 'Todavía no hay contenido: aquí verás cómo queda la sección.'));
      return;
    }

    previa.classList.toggle('sobre-nosotros--con-foto', urlFoto !== '');
    if (urlFoto !== '') {
      var figura = elemento('figure', 'sobre-nosotros-foto');
      var img = document.createElement('img');
      img.src = urlFoto;
      img.alt = '';
      figura.appendChild(img);
      previa.appendChild(figura);
    }

    partesIntro.forEach(function (lineas) {
      var p = elemento('p', 'texto-largo-parrafo');
      lineas.forEach(function (linea, k) {
        if (k > 0) { p.appendChild(document.createElement('br')); }
        p.appendChild(document.createTextNode(linea));
      });
      previa.appendChild(p);
    });

    if (textoSub !== '') {
      previa.appendChild(elemento('h3', 'texto-largo-subtitulo', conDosPuntos(textoSub)));
    }

    if (tarjetas.length > 0) {
      var lista = elemento('ul', 'texto-largo-lista');
      tarjetas.forEach(function (t) {
        var li = elemento('li', 'texto-largo-item');
        if (t.descripcion !== '') {
          li.appendChild(elemento('strong', '', conDosPuntos(t.titulo)));
          li.appendChild(document.createTextNode(' ' + t.descripcion));
        } else {
          li.textContent = t.titulo;
        }
        lista.appendChild(li);
      });
      previa.appendChild(lista);
    }

    if (textoCierre !== '') {
      previa.appendChild(elemento('p', 'texto-largo-cierre', textoCierre));
    }
  }

  [intro, subtitulo, cierre, quitarFoto, document.getElementById('sobre_nosotros_foto_alt')].forEach(function (campo) {
    if (campo) { campo.addEventListener('input', pintarPrevia); campo.addEventListener('change', pintarPrevia); }
  });
  contenedor.addEventListener('input', pintarPrevia);

  // Un envío con tarjetas ocultas no manda nada raro: están vacías. Solo se evita enviar con una foto marcada como "quitar" y otra nueva a la vez.
  if (form && quitarFoto && foto) {
    foto.addEventListener('change', function () { if (foto.files && foto.files[0]) { quitarFoto.checked = false; } });
  }

  refrescar();
}
