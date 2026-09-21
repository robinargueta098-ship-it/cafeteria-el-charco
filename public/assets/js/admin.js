/**
 * JavaScript del panel administrativo. Solo mejoras de interfaz
 * (mostrar/ocultar campos según una selección) — toda la validación
 * real ocurre en el servidor (App\Core\Validator y cada controlador),
 * esto es puramente para que el formulario sea más claro de llenar.
 */
document.addEventListener('DOMContentLoaded', function () {
  inicializarAlcancePromocion();
  inicializarTipoPromocion();
  inicializarSubidaGaleria();
});

/**
 * Subida de varias fotos: avisa de inmediato si alguna es muy pesada, tiene
 * un formato no permitido o son demasiadas, en vez de esperar a que el
 * servidor rechace todo el envío. Es solo comodidad — el servidor valida
 * cada archivo de nuevo (tipo real, tamaño) y es quien decide.
 */
function inicializarSubidaGaleria() {
  var input = document.getElementById('imagenes');
  var resumen = document.getElementById('resumen-subida');
  var boton = document.getElementById('boton-subir');
  if (!input || !resumen || !boton) {
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
