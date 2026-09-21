/**
 * JavaScript del panel administrativo. Solo mejoras de interfaz
 * (mostrar/ocultar campos según una selección) — toda la validación
 * real ocurre en el servidor (App\Core\Validator y cada controlador),
 * esto es puramente para que el formulario sea más claro de llenar.
 */
document.addEventListener('DOMContentLoaded', function () {
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
