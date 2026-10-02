/**
 * JavaScript del sitio público. Solo mejoras de interfaz (menú en móvil
 * y controles del carrusel de la galería) — nada aquí es indispensable
 * para ver el contenido: sin JavaScript el menú y el carrusel siguen
 * siendo usables (el carrusel se desliza con el dedo o la rueda).
 */
document.addEventListener('DOMContentLoaded', function () {
  // Campos de teléfono: solo números (y como máximo los que permite maxlength) mientras se escribe o se pega.
  document.querySelectorAll('input[data-solo-digitos]').forEach(function (campo) {
    campo.addEventListener('input', function () {
      var limpio = campo.value.replace(/\D/g, '');
      if (limpio !== campo.value) {
        campo.value = limpio;
      }
    });
  });

  inicializarMenuMovil();
  inicializarAnclas();
  inicializarHeroFondo();
  inicializarRevelado();
  inicializarRevelaDescripciones();
  inicializarRevelaFotos();
  inicializarMenuPestanas();
  document.querySelectorAll('[data-carrusel]').forEach(inicializarCarrusel);
});

/**
 * Aparición suave de las secciones al llegar al scroll (fade-in + pequeño
 * desplazamiento hacia arriba, ver ".por-revelar" en sitio.css). Las clases
 * las agrega ESTE código, nunca el HTML: así, si JavaScript no corre, nada
 * queda oculto (mejora progresiva). Usa IntersectionObserver nativo (sin
 * escuchar el evento "scroll" ni medir posiciones a mano) para no afectar
 * el rendimiento de la página.
 */
function inicializarRevelado() {
  if (!('IntersectionObserver' in window)) {
    return; // navegador muy viejo: el contenido ya es visible por defecto
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return; // se deja todo visible tal cual, sin animar
  }

  // Todas las secciones de la página (Más vendidos, Destacados, De temporada, Promociones,
  // Menú, Nosotros, Galería, Ubicación, Lealtad, Contacto…): cada una entra al llegar a ella.
  var secciones = Array.prototype.slice.call(document.querySelectorAll('section.sitio-seccion'));

  // Las tarjetas de las primeras secciones (todo menos el menú completo) entran además una
  // tras otra, un poco escalonadas: es el movimiento de "bienvenida" del inicio de la página.
  var tarjetas = Array.prototype.slice.call(
    document.querySelectorAll('section.sitio-seccion:not(#menu) .tarjeta-producto')
  );

  if (secciones.length === 0) {
    return;
  }

  // threshold bajo (no 0.15): algunas secciones (ej. #menu, con todo el
  // catálogo) miden varias veces la altura de la pantalla, así que pedir
  // que se vea el 15% de la sección completa nunca se cumplía y esa
  // sección se quedaba invisible para siempre. Con un umbral casi en 0
  // basta con que asome el borde de la sección.
  var observador = new IntersectionObserver(function (entradas, obs) {
    entradas.forEach(function (entrada) {
      if (entrada.isIntersecting) {
        entrada.target.classList.add('revelado');
        obs.unobserve(entrada.target); // ya apareció una vez: no hay que seguir observándola
      }
    });
  }, { threshold: 0.01, rootMargin: '0px 0px -40px 0px' });

  secciones.forEach(function (seccion) {
    seccion.classList.add('por-revelar');
    observador.observe(seccion);
  });

  tarjetas.forEach(function (tarjeta, indice) {
    tarjeta.classList.add('por-revelar');
    tarjeta.style.transitionDelay = ((indice % 4) * 110) + 'ms';
    observador.observe(tarjeta);
  });
}

/**
 * Enlaces con ancla (/#cat-cafe, /#menu…). El navegador hace el salto ANTES de que terminen de cargar las
 * imágenes y fuentes, y el contenido que cambia de tamaño después deja el destino desfasado ("a veces el
 * desplazamiento no ocurre del todo"). Se mide la barra fija y, cuando todo cargó, se vuelve a alinear
 * el destino una vez, salvo que la persona ya haya empezado a moverse por su cuenta.
 */
function inicializarAnclas() {
  var encabezado = document.querySelector('.sitio-nav');
  if (encabezado) {
    var alto = function () {
      document.documentElement.style.setProperty('--alto-nav', Math.round(encabezado.getBoundingClientRect().height) + 'px');
    };
    alto();
    window.addEventListener('resize', alto);
  }

  var seMovio = false;
  ['wheel', 'touchstart', 'mousedown', 'keydown'].forEach(function (evento) {
    window.addEventListener(evento, function () { seMovio = true; }, { passive: true, once: true });
  });

  function alinear() {
    if (seMovio || !window.location.hash) {
      return;
    }
    var destino = null;
    try {
      destino = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
    } catch (e) { /* ancla mal formada */ }
    if (!destino) {
      return;
    }
    var margen = parseFloat(window.getComputedStyle(destino).scrollMarginTop) || 0;
    var desfase = destino.getBoundingClientRect().top - margen;
    if (Math.abs(desfase) > 6) {
      window.scrollTo({ top: Math.max(0, window.pageYOffset + desfase), behavior: 'instant' });
      window.dispatchEvent(new Event('scroll')); // para que la pestaña activa del menú se ponga al día de inmediato
    }
  }

  window.addEventListener('load', function () {
    alinear();
    window.setTimeout(alinear, 400);
  });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(alinear);
  }
}

/**
 * Menú público en el teléfono: la barra de categorías pasa a ser una barra fija con pestañas.
 *  - Tocar una pestaña baja suavemente hasta esa categoría, que queda justo debajo de la barra.
 *  - Al desplazarse con el dedo, la pestaña de la categoría que se está viendo se marca sola y la
 *    barra se desliza de lado para mantenerla a la vista.
 * Todas las categorías siguen en la página (una debajo de otra): nada se oculta ni se reemplaza.
 * Las categorías salen del HTML del servidor (las de la base de datos), así que agregar o quitar
 * categorías en el panel no requiere tocar este código. En escritorio, o sin JavaScript, la barra
 * sigue siendo una lista de enlaces de ancla.
 */
function inicializarMenuPestanas() {
  var barra = document.querySelector('[data-menu-pestanas]');
  var enlaces = barra ? Array.prototype.slice.call(barra.querySelectorAll('a[href^="#cat-"]')) : [];
  var paneles = enlaces.map(function (e) { return document.getElementById(e.getAttribute('href').slice(1)); });
  if (!barra || enlaces.length === 0 || paneles.indexOf(null) !== -1 || !window.matchMedia) {
    return;
  }

  var movil = window.matchMedia('(max-width: 767px)');
  var encendido = false;
  var activa = -1;
  var bloqueoHasta = 0;   // mientras dura el desplazamiento provocado por un toque, no se re-marca
  var esperando = false;
  var encabezado = document.querySelector('.sitio-nav');

  function altoEncabezado() {
    return encabezado ? encabezado.getBoundingClientRect().height : 0;
  }

  // Línea bajo la cual "empieza" lo visible: encabezado fijo + barra de pestañas.
  function lineaSuperior() {
    return altoEncabezado() + barra.getBoundingClientRect().height + 8;
  }

  // Publica las alturas reales de las barras fijas para que CSS (scroll-margin-top) deje los títulos
  // de las anclas bien visibles debajo de ellas.
  function medirBarras() {
    var raiz = document.documentElement;
    raiz.style.setProperty('--alto-nav', Math.round(altoEncabezado()) + 'px');
    raiz.style.setProperty('--alto-pestanas', encendido ? Math.round(barra.getBoundingClientRect().height) + 'px' : '0px');
  }

  function centrar(enlace, suave) {
    var cajaEnlace = enlace.getBoundingClientRect();
    var cajaBarra = barra.getBoundingClientRect();
    var destino = barra.scrollLeft + (cajaEnlace.left - cajaBarra.left) - (barra.clientWidth - cajaEnlace.width) / 2;
    if (barra.scrollTo) {
      barra.scrollTo({ left: Math.max(0, destino), behavior: suave ? 'smooth' : 'auto' });
    } else {
      barra.scrollLeft = Math.max(0, destino);
    }
  }

  function marcar(indice, suave) {
    if (indice === activa) {
      return;
    }
    activa = indice;
    enlaces.forEach(function (enlace, i) {
      enlace.setAttribute('aria-selected', i === indice ? 'true' : 'false');
    });
    centrar(enlaces[indice], suave);
  }

  // La categoría "actual" es la última cuyo encabezado ya pasó la línea de la barra.
  function categoriaVisible() {
    var linea = lineaSuperior() + 4;
    var actual = 0;
    paneles.forEach(function (panel, i) {
      if (panel.getBoundingClientRect().top <= linea) {
        actual = i;
      }
    });
    return actual;
  }

  function alDesplazar() {
    if (esperando || Date.now() < bloqueoHasta) {
      return;
    }
    esperando = true;
    requestAnimationFrame(function () {
      esperando = false;
      marcar(categoriaVisible(), true);
    });
  }

  function alTocar(evento) {
    evento.preventDefault();
    var indice = enlaces.indexOf(evento.currentTarget);
    var destino = paneles[indice].getBoundingClientRect().top + window.pageYOffset - lineaSuperior() + 4;

    bloqueoHasta = Date.now() + 900;   // dura lo que tarda el desplazamiento suave
    marcar(indice, true);
    window.scrollTo({ top: Math.max(0, destino), behavior: 'smooth' });
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, '', enlaces[indice].getAttribute('href'));
    }
  }

  function alTeclear(evento) {
    var indice = enlaces.indexOf(evento.currentTarget);
    var siguiente = null;
    if (evento.key === 'ArrowRight') { siguiente = Math.min(enlaces.length - 1, indice + 1); }
    if (evento.key === 'ArrowLeft') { siguiente = Math.max(0, indice - 1); }
    if (siguiente !== null) {
      evento.preventDefault();
      enlaces[siguiente].focus();
    }
  }

  function encender() {
    encendido = true;
    barra.classList.add('menu-nav--pestanas');
    barra.setAttribute('role', 'tablist');
    enlaces.forEach(function (enlace) {
      enlace.setAttribute('role', 'tab');
      enlace.addEventListener('click', alTocar);
      enlace.addEventListener('keydown', alTeclear);
    });
    medirBarras();
    window.addEventListener('scroll', alDesplazar, { passive: true });
    activa = -1;
    marcar(categoriaVisible(), false);
  }

  function apagar() {
    encendido = false;
    barra.classList.remove('menu-nav--pestanas');
    barra.removeAttribute('role');
    enlaces.forEach(function (enlace) {
      enlace.removeAttribute('role');
      enlace.removeAttribute('aria-selected');
      enlace.removeEventListener('click', alTocar);
      enlace.removeEventListener('keydown', alTeclear);
    });
    medirBarras();
    window.removeEventListener('scroll', alDesplazar);
    activa = -1;
  }

  function sincronizar() {
    if (movil.matches && !encendido) {
      encender();
    } else if (!movil.matches && encendido) {
      apagar();
    } else {
      medirBarras();
    }
  }

  sincronizar();

  // Al terminar de cargar (y de alinear un enlace #cat-…) la pestaña marcada debe coincidir con lo que se ve.
  window.addEventListener('load', function () {
    if (encendido) {
      marcar(categoriaVisible(), false);
    }
  });
  if (movil.addEventListener) {
    movil.addEventListener('change', sincronizar);
  } else if (movil.addListener) {
    movil.addListener(sincronizar);
  }

  // Respaldo: cambiar el tamaño de la ventana, girar el teléfono o abrir/cerrar el modo móvil de
  // las herramientas de desarrollo no siempre dispara "change" a tiempo.
  var pendiente = null;
  function alCambiarTamano() {
    clearTimeout(pendiente);
    pendiente = setTimeout(sincronizar, 120);
  }
  window.addEventListener('resize', alCambiarTamano);
  window.addEventListener('orientationchange', alCambiarTamano);
}

/**
 * Las fotos de las tarjetas (temporada, promociones, productos) suben suavemente desde abajo
 * cuando están a la vista Y ya terminaron de descargarse. Las imágenes usan carga diferida
 * (loading="lazy"), así que sin esto aparecían de golpe al terminar de cargar.
 */
function inicializarRevelaFotos() {
  if (!('IntersectionObserver' in window)) {
    return;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var fotos = document.querySelectorAll('.tarjeta-producto > img, .tarjeta-producto > picture img');
  if (fotos.length === 0) {
    return;
  }

  function mostrar(foto) {
    // Dos fotogramas: el navegador pinta primero el estado oculto y la transición sí se ve.
    requestAnimationFrame(function () {
      requestAnimationFrame(function () { foto.classList.add('img-lista'); });
    });
  }

  var observador = new IntersectionObserver(function (entradas, obs) {
    entradas.forEach(function (entrada) {
      if (!entrada.isIntersecting) {
        return;
      }
      var foto = entrada.target;
      obs.unobserve(foto);
      if (foto.complete && foto.naturalWidth > 0) {
        mostrar(foto);
      } else {
        foto.addEventListener('load', function () { mostrar(foto); }, { once: true });
        foto.addEventListener('error', function () { foto.classList.add('img-lista'); }, { once: true });
      }
    });
  }, { threshold: 0.1 });

  Array.prototype.forEach.call(fotos, function (foto) {
    foto.classList.add('img-por-cargar');
    observador.observe(foto);
  });
}

/**
 * Cada descripción de producto aparece sola (fade-in + subida suave) al entrar en pantalla,
 * con un pequeño escalonado entre las tarjetas de una misma fila. Reutiliza las clases
 * ".por-revelar" / ".revelado" de la sección Promociones (inicializarRevelado) y respeta
 * "reducir movimiento"; sin JavaScript las descripciones quedan visibles tal cual.
 */
function inicializarRevelaDescripciones() {
  if (!('IntersectionObserver' in window)) {
    return;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var descripciones = document.querySelectorAll('#menu .tarjeta-producto-descripcion');
  if (descripciones.length === 0) {
    return;
  }

  var observador = new IntersectionObserver(function (entradas, obs) {
    entradas.forEach(function (entrada) {
      if (entrada.isIntersecting) {
        entrada.target.classList.add('revelado');
        obs.unobserve(entrada.target);
      }
    });
  }, { threshold: 0.2, rootMargin: '0px 0px -30px 0px' });

  Array.prototype.forEach.call(descripciones, function (parrafo, indice) {
    parrafo.classList.add('por-revelar');
    parrafo.style.transitionDelay = ((indice % 3) * 90) + 'ms';
    observador.observe(parrafo);
  });
}

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
