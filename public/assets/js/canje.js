/*
 * Pantalla de canje del panel: lector de QR con la cámara (html5-qrcode,
 * copia local en /assets/vendor). Al leer un código lo pone en el mismo
 * campo que el cajero usaría a mano y envía el mismo formulario, así que
 * escanear y escribir siguen exactamente el mismo camino en el servidor.
 */
(function () {
  'use strict';

  var boton = document.getElementById('boton-escanear');
  var zona = document.getElementById('lector-qr');
  var mensaje = document.getElementById('lector-mensaje');
  var campo = document.getElementById('codigo');
  var formulario = document.getElementById('formulario-codigo');

  if (!boton || !zona || !campo || !formulario) {
    return;
  }

  var lector = null;
  var activo = false;

  function decir(texto) {
    mensaje.textContent = texto;
  }

  function sinCamara(texto) {
    decir(texto + ' Escribe el código en el campo de al lado.');
    campo.focus();
  }

  function detener() {
    var actual = lector;
    activo = false;
    boton.textContent = 'Escanear código';
    zona.hidden = true;
    if (!actual) {
      return Promise.resolve();
    }
    lector = null;
    return actual.stop().catch(function () {}).then(function () {
      try { actual.clear(); } catch (e) { /* ya estaba limpio */ }
    });
  }

  function alLeer(texto) {
    if (!activo) {
      return; // el lector puede repetir la lectura mientras se detiene
    }
    campo.value = String(texto).trim();
    decir('Código leído. Buscando…');
    detener().then(function () {
      formulario.submit();
    });
  }

  function iniciar() {
    // getUserMedia solo existe en páginas seguras (HTTPS, o localhost). En producción el
    // sitio va por HTTPS y el navegador pide el permiso de la cámara al abrirla; en local
    // se usa http://elcharco.localhost (el navegador lo trata como localhost).
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      sinCamara('El navegador no permite usar la cámara en esta dirección (necesita HTTPS o localhost; en local usa http://elcharco.localhost).');
      return;
    }
    if (typeof Html5Qrcode === 'undefined') {
      sinCamara('No se pudo cargar el lector de QR.');
      return;
    }

    zona.hidden = false;
    boton.textContent = 'Detener cámara';
    decir('Apunta la cámara al código QR del cliente…');
    activo = true;
    lector = new Html5Qrcode('lector-qr', { formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] });

    lector.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: function (ancho, alto) { var lado = Math.floor(Math.min(ancho, alto) * 0.8); return { width: lado, height: lado }; } },
      alLeer,
      function () { /* fotogramas sin QR: es lo normal, no es un error */ }
    ).catch(function (error) {
      var nombre = error && error.name ? error.name : String(error);
      var texto = 'No se pudo abrir la cámara.';
      if (/NotAllowed|Permission/i.test(nombre + error)) {
        texto = 'No diste permiso para usar la cámara.';
      } else if (/NotFound|Devices/i.test(nombre + error)) {
        texto = 'No se encontró ninguna cámara.';
      } else if (/NotReadable|TrackStart/i.test(nombre + error)) {
        texto = 'La cámara está siendo usada por otra aplicación.';
      }
      detener().then(function () { sinCamara(texto); });
    });
  }

  boton.addEventListener('click', function () {
    if (activo) {
      detener().then(function () { decir('Cámara detenida.'); });
    } else {
      iniciar();
    }
  });

  // Al cerrar o cambiar de pantalla, la cámara se apaga.
  window.addEventListener('pagehide', function () { detener(); });
})();
