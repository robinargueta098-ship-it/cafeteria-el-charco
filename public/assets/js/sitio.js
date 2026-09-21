/**
 * JavaScript del sitio público. Solo mejoras de interfaz (menú en móvil
 * y controles del carrusel de la galería) — nada aquí es indispensable
 * para ver el contenido: sin JavaScript el menú y el carrusel siguen
 * siendo usables (el carrusel se desliza con el dedo o la rueda).
 */
document.addEventListener('DOMContentLoaded', function () {
  inicializarMenuMovil();
  inicializarHeroFondo();
  document.querySelectorAll('[data-carrusel]').forEach(inicializarCarrusel);
});

/**
 * Fondo automático del hero (sin controles): funde una foto con la
 * siguiente cada 5 s. Cuida el rendimiento:
 *  - solo la primera foto viene en el HTML; las demás se cargan de a una,
 *    DESPUÉS de que la página terminó de cargar, y nunca se muestra una
 *    que aún no llegó (si la red es lenta, simplemente espera);
 *  - no se mueve si el usuario pidió "reducir movimiento" ni en modo
 *    ahorro de datos / conexión 2G (se queda la primera foto fija, y ni
 *    siquiera se descargan las demás);
 *  - se pausa cuando el hero no está en pantalla o la pestaña está oculta.
 */
function inicializarHeroFondo() {
  var fondo = document.querySelector('[data-hero-fondo]');
  if (!fondo) {
    return;
  }

  var slides = Array.prototype.slice.call(fondo.querySelectorAll('.hero-slide'));
  if (slides.length < 2) {
    return;
  }

  var conexion = navigator.connection || {};
  var ahorroDeDatos = conexion.saveData === true || /(^|-)2g$/.test(conexion.effectiveType || '');
  var reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (ahorroDeDatos || reducirMovimiento) {
    return;
  }

  var INTERVALO_MS = 5000;
  var FUNDIDO_MS = 1700; // igual que la transición de .hero-slide en sitio.css

  var actual = 0;
  var enTransicion = false;
  var esperandoCarga = false;
  var visible = true;

  slides[0].setAttribute('data-cargada', '1');

  function cargar(indice, alTerminar) {
    var img = slides[indice];

    if (img.getAttribute('data-cargada') === '1') {
      alTerminar(true);
      return;
    }

    img.addEventListener('load', function () {
      img.setAttribute('data-cargada', '1');
      alTerminar(true);
    }, { once: true });
    img.addEventListener('error', function () { alTerminar(false); }, { once: true });

    // srcset antes que src: así el navegador elige la versión adecuada al tamaño de pantalla.
    var srcset = img.getAttribute('data-srcset');
    if (srcset) {
      img.srcset = srcset;
    }
    img.src = img.getAttribute('data-src');
  }

  function mostrar(indice) {
    var anterior = slides[actual];

    slides[indice].classList.add('activo');
    anterior.classList.remove('activo');
    anterior.classList.add('saliente'); // se queda debajo, opaca, mientras entra la nueva

    actual = indice;
    enTransicion = true;

    window.setTimeout(function () {
      anterior.classList.remove('saliente');
      enTransicion = false;
    }, FUNDIDO_MS + 100);
  }

  function siguiente() {
    if (enTransicion || esperandoCarga || !visible || document.hidden) {
      return;
    }

    var indice = (actual + 1) % slides.length;
    esperandoCarga = true;

    cargar(indice, function (ok) {
      esperandoCarga = false;
      if (ok && !enTransicion && indice !== actual) {
        mostrar(indice);
      }
    });
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entradas) {
      visible = entradas[0].isIntersecting;
    }).observe(fondo.parentNode);
  }

  function empezar() {
    // Adelanta la 2.ª foto para que esté lista al primer cambio, sin competir con la carga inicial.
    cargar(1, function () {});
    window.setInterval(siguiente, INTERVALO_MS);
  }

  if (document.readyState === 'complete') {
    window.setTimeout(empezar, 800);
  } else {
    window.addEventListener('load', function () { window.setTimeout(empezar, 800); });
  }
}

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
