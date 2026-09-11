/**
 * JavaScript del panel administrativo. Solo mejoras de interfaz
 * (mostrar/ocultar campos según una selección) — toda la validación
 * real ocurre en el servidor (App\Core\Validator y cada controlador),
 * esto es puramente para que el formulario sea más claro de llenar.
 */
document.addEventListener('DOMContentLoaded', function () {
  inicializarAlcancePromocion();
  inicializarTipoPromocion();
});

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
