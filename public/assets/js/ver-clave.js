/*
 * Botón «ver / ocultar» en todos los campos de contraseña o PIN. Mejora progresiva: sin JavaScript el campo
 * funciona igual (oculto). Vuelve a ocultarse solo al enviar el formulario o al salir de la pestaña.
 */
(function () {
  'use strict';

  var OJO = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var OJO_TACHADO = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19C5 19 1 12 1 12a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.1 9.1 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

  var campos = [];

  function preparar(entrada) {
    if (entrada.getAttribute('data-ver-clave') === '1') { return; }
    entrada.setAttribute('data-ver-clave', '1');

    var envoltura = document.createElement('span');
    envoltura.className = 'campo-clave';
    entrada.parentNode.insertBefore(envoltura, entrada);
    envoltura.appendChild(entrada);

    var boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'btn-ver-clave';
    boton.setAttribute('aria-pressed', 'false');
    boton.setAttribute('aria-label', 'Mostrar contraseña');
    boton.title = 'Mostrar contraseña';
    boton.innerHTML = OJO;
    envoltura.appendChild(boton);

    function ocultar() {
      entrada.type = 'password';
      boton.setAttribute('aria-pressed', 'false');
      boton.setAttribute('aria-label', 'Mostrar contraseña');
      boton.title = 'Mostrar contraseña';
      boton.innerHTML = OJO;
    }

    boton.addEventListener('click', function () {
      var mostrar = entrada.type === 'password';
      if (!mostrar) { ocultar(); entrada.focus(); return; }
      entrada.type = 'text';
      boton.setAttribute('aria-pressed', 'true');
      boton.setAttribute('aria-label', 'Ocultar contraseña');
      boton.title = 'Ocultar contraseña';
      boton.innerHTML = OJO_TACHADO;
      entrada.focus();
    });

    if (entrada.form) {
      entrada.form.addEventListener('submit', ocultar);
    }
    campos.push(ocultar);
  }

  function iniciar() {
    var lista = document.querySelectorAll('input[type="password"]');
    for (var i = 0; i < lista.length; i++) { preparar(lista[i]); }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { campos.forEach(function (ocultar) { ocultar(); }); }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
