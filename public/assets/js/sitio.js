/**
 * JavaScript del sitio público. Solo una mejora de interfaz (abrir/
 * cerrar el menú en móvil) — nada aquí es indispensable para ver el
 * contenido, así que el sitio funciona igual sin JavaScript.
 */
document.addEventListener('DOMContentLoaded', function () {
  var boton = document.querySelector('.sitio-nav-boton-menu');
  var enlaces = document.querySelector('.sitio-nav-enlaces');

  if (!boton || !enlaces) {
    return;
  }

  boton.addEventListener('click', function () {
    var abierto = enlaces.classList.toggle('abierto');
    boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
  });

  // Cierra el menú al elegir una sección (útil en móvil).
  enlaces.querySelectorAll('a').forEach(function (enlace) {
    enlace.addEventListener('click', function () {
      enlaces.classList.remove('abierto');
      boton.setAttribute('aria-expanded', 'false');
    });
  });
});
