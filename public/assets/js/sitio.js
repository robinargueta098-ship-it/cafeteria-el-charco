/**
 * JavaScript del sitio público. Solo mejoras de interfaz (menú en móvil
 * y controles del carrusel de la galería) — nada aquí es indispensable
 * para ver el contenido: sin JavaScript el menú y el carrusel siguen
 * siendo usables (el carrusel se desliza con el dedo o la rueda).
 */
document.addEventListener('DOMContentLoaded', function () {
  inicializarMenuMovil();
  document.querySelectorAll('[data-carrusel]').forEach(inicializarCarrusel);
});

function inicializarMenuMovil() {
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
}

/**
 * Carrusel de la galería. La pista ya se desliza sola (scroll-snap en CSS);
 * esto agrega flechas, puntos y avance automático encima de eso.
 */
function inicializarCarrusel(carrusel) {
  var pista = carrusel.querySelector('.carrusel-pista');
  var items = Array.prototype.slice.call(carrusel.querySelectorAll('.carrusel-item'));
  var anterior = carrusel.querySelector('.carrusel-anterior');
  var siguiente = carrusel.querySelector('.carrusel-siguiente');
  var contenedorPuntos = carrusel.querySelector('.carrusel-puntos');

  if (!pista || items.length === 0) {
    return;
  }

  var reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var comportamiento = reducirMovimiento ? 'auto' : 'smooth';
  var puntos = [];
  var detenido = false; // true = el usuario ya interactuó: no volver a avanzar solo
  var pausado = false;  // pausa temporal (mouse encima, foco dentro)
  var pendiente = false;

  carrusel.classList.add('carrusel--js');

  function hayDesbordamiento() {
    return pista.scrollWidth > pista.clientWidth + 2;
  }

  function alFinal() {
    return pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - 2;
  }

  function indiceActual() {
    if (alFinal()) {
      return items.length - 1;
    }
    var mejor = 0;
    var menor = Infinity;
    items.forEach(function (item, i) {
      var distancia = Math.abs(item.offsetLeft - pista.scrollLeft);
      if (distancia < menor) {
        menor = distancia;
        mejor = i;
      }
    });
    return mejor;
  }

  function maximoScroll() {
    return Math.max(0, pista.scrollWidth - pista.clientWidth);
  }

  /**
   * Desplaza la pista a una posición absoluta. Si el navegador ignora la
   * animación suave (se observó en un viewport móvil emulado: pedía el
   * desplazamiento y no se movía nada), lo repite sin animar tras un
   * momento, para que los botones nunca queden "sin efecto".
   */
  function desplazarA(destino) {
    destino = Math.min(Math.max(destino, 0), maximoScroll());
    var inicio = pista.scrollLeft;

    pista.scrollTo({ left: destino, behavior: comportamiento });

    if (comportamiento === 'smooth' && Math.abs(destino - inicio) > 2) {
      window.setTimeout(function () {
        if (Math.abs(pista.scrollLeft - inicio) < 1) {
          pista.scrollTo({ left: destino, behavior: 'auto' });
        }
      }, 900);
    }
  }

  function irA(indice) {
    desplazarA(items[indice].offsetLeft);
  }

  function avanzar(direccion) {
    if (direccion > 0) {
      desplazarA(alFinal() ? 0 : pista.scrollLeft + pista.clientWidth * 0.9); // al final, vuelve al inicio
    } else {
      desplazarA(pista.scrollLeft <= 2 ? maximoScroll() : pista.scrollLeft - pista.clientWidth * 0.9); // en el inicio, salta al final
    }
  }

  function actualizar() {
    pendiente = false;
    carrusel.classList.toggle('carrusel--corto', !hayDesbordamiento());

    var actual = indiceActual();
    puntos.forEach(function (punto, i) {
      var activo = i === actual;
      punto.classList.toggle('activo', activo);
      if (activo) {
        punto.setAttribute('aria-current', 'true');
      } else {
        punto.removeAttribute('aria-current');
      }
    });
  }

  function programarActualizacion() {
    if (!pendiente) {
      pendiente = true;
      window.requestAnimationFrame(actualizar);
    }
  }

  if (contenedorPuntos) {
    items.forEach(function (_, i) {
      var punto = document.createElement('button');
      punto.type = 'button';
      punto.className = 'carrusel-punto';
      punto.setAttribute('aria-label', 'Ir a la foto ' + (i + 1) + ' de ' + items.length);
      punto.addEventListener('click', function () {
        detenido = true;
        irA(i);
      });
      contenedorPuntos.appendChild(punto);
      puntos.push(punto);
    });
  }

  if (anterior) {
    anterior.addEventListener('click', function () { detenido = true; avanzar(-1); });
  }
  if (siguiente) {
    siguiente.addEventListener('click', function () { detenido = true; avanzar(1); });
  }

  pista.addEventListener('scroll', programarActualizacion, { passive: true });
  window.addEventListener('resize', programarActualizacion);
  window.addEventListener('load', programarActualizacion);

  // Recalcula cada vez que cambia el tamaño real de la pista o de las fotos
  // (rotar el teléfono, cargar una fuente, cambiar el zoom). Sin esto, una
  // medición tomada a mitad de un cambio de diseño podía dejar el carrusel
  // "marcado como corto" (sin flechas) para siempre.
  if (typeof ResizeObserver !== 'undefined') {
    var observador = new ResizeObserver(programarActualizacion);
    observador.observe(pista);
    items.forEach(function (item) { observador.observe(item); });
  }

  // Si el usuario toca, arrastra o usa el teclado, deja de avanzar solo.
  ['pointerdown', 'touchstart', 'keydown', 'wheel'].forEach(function (evento) {
    pista.addEventListener(evento, function () { detenido = true; }, { passive: true });
  });

  // Pausa mientras el mouse está encima o el foco está dentro.
  carrusel.addEventListener('mouseenter', function () { pausado = true; });
  carrusel.addEventListener('mouseleave', function () { pausado = false; });
  carrusel.addEventListener('focusin', function () { pausado = true; });
  carrusel.addEventListener('focusout', function () { pausado = false; });

  // Avance automático: no si el usuario pidió menos movimiento, si ya
  // interactuó, si la pestaña está oculta o si no hay nada que desplazar.
  if (!reducirMovimiento) {
    window.setInterval(function () {
      if (detenido || pausado || document.hidden || !hayDesbordamiento()) {
        return;
      }
      avanzar(1);
    }, 5000);
  }

  actualizar();
}
