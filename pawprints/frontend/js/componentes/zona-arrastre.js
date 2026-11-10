/**
 * Componente de zona de arrastre para carga de archivos (TP4, consigna 3).
 *
 * Convierte un campo de archivo estándar (<input type="file">) en una zona interactiva
 * accesible que permite arrastrar y soltar o seleccionar mediante clic y teclado.
 *
 * Uso: new ZonaArrastre(contenedor)
 *
 * Características:
 * - Oculta visualmente el input nativo pero lo conserva en el DOM.
 * - Validación de tipo MIME (JPG, PNG, WebP) y tamaño (hasta 2 MB) antes de aceptarlo.
 * - Muestra errores en línea idénticos a validador.js con aria-invalid y aria-describedby.
 * - Vista previa con URL.createObjectURL() y botón para quitarla con revokeObjectURL().
 * - Sincroniza el archivo con DataTransfer para que viaje normalmente con el formulario.
 */

const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];
const TAMANO_MAXIMO = 2 * 1024 * 1024; // 2 MB (upload_max_filesize por defecto de PHP)

const MENSAJES_DEFECTO = {
  tipo: 'La tapa debe ser una imagen en formato JPG, PNG o WebP.',
  tamano: 'La imagen no puede pesar más de 2 MB.',
};

export class ZonaArrastre {
  /**
   * @param {HTMLElement} contenedor El elemento que envuelve el <input type="file">.
   */
  constructor(contenedor) {
    if (!(contenedor instanceof HTMLElement)) {
      console.warn('ZonaArrastre: no se encontró el contenedor; no se arma nada.');
      return;
    }

    this.input = contenedor.querySelector('input[type="file"]');
    if (!(this.input instanceof HTMLInputElement)) {
      console.warn('ZonaArrastre: el contenedor no tiene un <input type="file">; no se arma nada.', contenedor);
      return;
    }

    this.contenedor = contenedor;
    this.texto = contenedor.dataset.texto ?? 'Arrastrá la tapa acá o hacé clic para elegirla';
    this.tiposPermitidos = contenedor.dataset.tipos
      ? contenedor.dataset.tipos.split(',').map((t) => t.trim())
      : TIPOS_PERMITIDOS;
    this.tamanoMaximo = Number(contenedor.dataset.maxTamano) || TAMANO_MAXIMO;
    this.mensajes = {
      tipo: contenedor.dataset.errorTipo ?? MENSAJES_DEFECTO.tipo,
      tamano: contenedor.dataset.errorTamano ?? MENSAJES_DEFECTO.tamano,
    };

    this.urlVistaPrevia = null;
    this.sincronizando = false;

    this.armar();
    this.escuchar();
  }

  // ---- Estructura --------------------------------------------------------------------------

  armar() {
    // Se oculta el input visualmente pero se mantiene accesible para el formulario y lectores.
    this.input.classList.add('zona-arrastre-input-oculto');

    // Se crea la zona de arrastre accesible con tabindex y rol de botón.
    this.zona = document.createElement('div');
    this.zona.className = 'zona-arrastre';
    this.zona.tabIndex = 0;
    this.zona.setAttribute('role', 'button');
    this.zona.setAttribute('aria-label', this.texto);

    // Se arma con nodos y no con innerHTML: el texto de la instrucción sale de data-texto, y
    // con textContent un < o un & se ven como tales en lugar de interpretarse como HTML. Así
    // ningún dato que llegue al componente puede agregar elementos o scripts a la página.
    this.zona.append(this.armarContenido(), this.armarPreview());


    this.elContenido = this.zona.querySelector('.zona-arrastre-contenido');
    this.elPreview = this.zona.querySelector('.zona-arrastre-preview');
    this.elImg = this.zona.querySelector('.zona-arrastre-img');
    this.elNombre = this.zona.querySelector('.zona-arrastre-nombre');
    this.elTamano = this.zona.querySelector('.zona-arrastre-tamano');
    this.elBotonQuitar = this.zona.querySelector('.zona-arrastre-quitar');
    this.elAviso = this.zona.querySelector('.zona-arrastre-aviso');

    // Se inserta después del input en el contenedor.
    this.input.after(this.zona);

    // Si el servidor devolvió un aviso de reingreso por errores en otros campos, se muestra en el componente.
    const avisoServidor = this.contenedor.querySelector('.aviso-reingreso-tapa');
    if (avisoServidor && avisoServidor.textContent.trim()) {
      this.avisar(avisoServidor.textContent.trim());
      avisoServidor.hidden = true;
    }

    // Si el servidor devolvió un error para la tapa, se marca la zona como inválida.
    const errorExistente = document.getElementById(this.idDeError());
    if (errorExistente && !errorExistente.hidden && errorExistente.textContent.trim()) {
      this.zona.setAttribute('aria-invalid', 'true');
      this.describir(errorExistente.id, true);
    }
  }

  /** El ícono, la instrucción y el aviso, que se ven mientras no hay una tapa elegida. */
  armarContenido() {
    const contenido = elemento('div', 'zona-arrastre-contenido');

    const icono = elemento('div', 'zona-arrastre-icono');
    icono.setAttribute('aria-hidden', 'true');
    icono.append(iconoImagen());

    const instruccion = elemento('p', 'zona-arrastre-instruccion');
    instruccion.textContent = this.texto;

    const aviso = elemento('p', 'zona-arrastre-aviso');
    aviso.setAttribute('role', 'status');
    aviso.setAttribute('aria-live', 'assertive');
    aviso.hidden = true;

    contenido.append(icono, instruccion, aviso);
    return contenido;
  }

  /** La vista previa de la tapa elegida, con su nombre, su tamaño y el botón para quitarla. */
  armarPreview() {
    const preview = elemento('div', 'zona-arrastre-preview');
    preview.hidden = true;

    const img = elemento('img', 'zona-arrastre-img');
    img.alt = '';

    const detalles = elemento('div', 'zona-arrastre-detalles');
    const quitar = elemento('button', 'zona-arrastre-quitar');
    quitar.type = 'button';
    quitar.setAttribute('aria-label', 'Quitar imagen de tapa y elegir otra');
    quitar.textContent = 'Quitar tapa';
    detalles.append(elemento('p', 'zona-arrastre-nombre'), elemento('p', 'zona-arrastre-tamano'), quitar);

    preview.append(img, detalles);
    return preview;
  }

  // ---- Eventos y Comportamiento ------------------------------------------------------------

  escuchar() {
    // 1. Apertura del selector al hacer clic sobre la zona (excepto si se clickea el botón de quitar).
    this.zona.addEventListener('click', (e) => {
      if (e.target.closest('.zona-arrastre-quitar')) return;
      this.input.click();
    });

    // 2. Accesibilidad por teclado: Enter o Barra Espaciadora abren el selector.
    this.zona.addEventListener('keydown', (e) => {
      if (e.target.closest('.zona-arrastre-quitar')) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.input.click();
      }
    });

    // 3. Botón para quitar la vista previa y elegir otra.
    this.elBotonQuitar.addEventListener('click', (e) => {
      e.stopPropagation();
      this.quitar();
      this.zona.focus();
    });

    // 4. Arrastre y soltado (Drag and Drop).
    let contadorArrastre = 0;

    this.zona.addEventListener('dragenter', (e) => {
      e.preventDefault();
      contadorArrastre++;
      this.zona.classList.add('arrastrando');
    });

    this.zona.addEventListener('dragover', (e) => {
      // preventDefault es indispensable para que el navegador no abra la imagen directamente.
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
      if (!this.zona.classList.contains('arrastrando')) {
        this.zona.classList.add('arrastrando');
      }
    });

    this.zona.addEventListener('dragleave', (e) => {
      e.preventDefault();
      contadorArrastre--;
      if (contadorArrastre <= 0) {
        contadorArrastre = 0;
        this.zona.classList.remove('arrastrando');
      }
    });

    this.zona.addEventListener('drop', (e) => {
      e.preventDefault();
      contadorArrastre = 0;
      this.zona.classList.remove('arrastrando');

      const archivos = e.dataTransfer?.files;
      if (!archivos || archivos.length === 0) return;

      this.procesarArchivos(archivos);
    });

    // 5. Cambio manual desde el selector nativo del sistema operativo.
    this.input.addEventListener('change', () => {
      if (this.sincronizando) return;
      const archivos = this.input.files;
      if (!archivos || archivos.length === 0) return;

      this.procesarArchivos(archivos);
    });
  }

  // ---- Validación --------------------------------------------------------------------------

  /**
   * Controla tipo MIME / extensión y tamaño en bytes.
   * Devuelve un string con el mensaje de error o '' si es válido.
   * @param {File} archivo
   * @returns {string}
   */
  validar(archivo) {
    const extension = archivo.name.split('.').pop()?.toLowerCase() ?? '';
    const extensionesValidas = ['jpg', 'jpeg', 'png', 'webp'];
    const esTipoValido = this.tiposPermitidos.includes(archivo.type) || extensionesValidas.includes(extension);

    if (!esTipoValido) {
      return this.mensajes.tipo;
    }

    if (archivo.size > this.tamanoMaximo) {
      return this.mensajes.tamano;
    }

    return '';
  }

  // ---- Procesamiento y Mensajes ------------------------------------------------------------

  /**
   * Procesa los archivos. Si sueltan varios toma el primero y avisa.
   * Controla tipo y tamaño antes de aceptarlo.
   * @param {FileList|File[]} archivos
   */
  procesarArchivos(archivos) {
    const primerArchivo = archivos[0];

    // Un solo archivo: si sueltan varios, toma el primero y avisa.
    if (archivos.length > 1) {
      this.avisar(`Se seleccionó sólo el primer archivo («${primerArchivo.name}»). Podés cargar una única tapa.`);
    } else {
      this.limpiarAviso();
    }

    // Control de tipo y tamaño antes de aceptarlo.
    const error = this.validar(primerArchivo);
    if (error) {
      // Un PDF o una imagen muy pesada muestra el error y no se carga.
      this.quitar(false);
      this.mostrarError(error);
      return;
    }

    // Si es válido, se borran errores previos.
    this.limpiarError();

    // Se asigna el archivo al input nativo usando DataTransfer.
    try {
      const dt = new DataTransfer();
      dt.items.add(primerArchivo);
      this.input.files = dt.files;
    } catch (err) {
      console.warn('ZonaArrastre: no se pudo asignar DataTransfer al input.', err);
    }

    // Se dibuja la vista previa con URL.createObjectURL.
    this.mostrarVistaPrevia(primerArchivo);

    // Notifica el cambio al formulario y otros escuchadores sin disparar recursión.
    this.notificarCambio();
  }

  /**
   * Muestra la vista previa del archivo creando una URL de objeto.
   * @param {File} archivo
   */
  mostrarVistaPrevia(archivo) {
    if (this.urlVistaPrevia) {
      URL.revokeObjectURL(this.urlVistaPrevia);
      this.urlVistaPrevia = null;
    }

    this.urlVistaPrevia = URL.createObjectURL(archivo);
    this.elImg.src = this.urlVistaPrevia;
    this.elImg.alt = `Vista previa de la tapa: ${archivo.name}`;
    this.elNombre.textContent = archivo.name;
    const tamanoKb = (archivo.size / 1024).toFixed(0);
    this.elTamano.textContent = `${tamanoKb} KB`;

    this.elContenido.hidden = true;
    this.elPreview.hidden = false;
    this.zona.classList.add('con-preview', 'con-archivo');
  }

  /**
   * Quita la vista previa, libera la URL con revokeObjectURL y vacía el input.
   * @param {boolean} limpiarError Si también se debe resetear el error mostrado.
   */
  quitar(limpiarError = true) {
    if (this.urlVistaPrevia) {
      URL.revokeObjectURL(this.urlVistaPrevia);
      this.urlVistaPrevia = null;
    }

    this.elImg.src = '';
    this.elImg.alt = '';
    this.elNombre.textContent = '';
    this.elTamano.textContent = '';

    this.elPreview.hidden = true;
    this.elContenido.hidden = false;
    this.zona.classList.remove('con-preview', 'con-archivo');

    this.input.value = '';
    try {
      this.input.files = new DataTransfer().files;
    } catch {}

    this.limpiarAviso();
    if (limpiarError) {
      this.limpiarError();
    }

    this.notificarCambio();
  }

  notificarCambio() {
    this.sincronizando = true;
    this.input.dispatchEvent(new Event('change', { bubbles: true }));
    this.input.dispatchEvent(new Event('input', { bubbles: true }));
    this.sincronizando = false;
  }

  // ---- Manejo de errores en línea (igual que validador.js) ----------------------------------

  idDeError() {
    return `error-${this.input.name}`;
  }

  elementoDeError() {
    let p = document.getElementById(this.idDeError());
    if (!p) {
      p = document.createElement('p');
      p.id = this.idDeError();
      p.className = 'error-campo';
      (this.input.closest('.campo') ?? this.contenedor).append(p);
    }
    return p;
  }

  mostrarError(mensaje) {
    const p = this.elementoDeError();
    p.textContent = mensaje;
    p.hidden = false;
    this.input.setAttribute('aria-invalid', 'true');
    this.zona.setAttribute('aria-invalid', 'true');
    this.describir(p.id, true);
  }

  limpiarError() {
    const p = document.getElementById(this.idDeError());
    if (p) {
      p.textContent = '';
      p.hidden = true;
    }
    this.input.removeAttribute('aria-invalid');
    this.zona.removeAttribute('aria-invalid');
    this.describir(this.idDeError(), false);
  }

  describir(id, incluir) {
    for (const el of [this.input, this.zona]) {
      const ids = new Set((el.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
      if (incluir) ids.add(id);
      else ids.delete(id);
      if (ids.size) el.setAttribute('aria-describedby', [...ids].join(' '));
      else el.removeAttribute('aria-describedby');
    }
  }

  // ---- Avisos secundarios ------------------------------------------------------------------

  avisar(mensaje) {
    this.elAviso.textContent = mensaje;
    this.elAviso.hidden = false;
  }

  limpiarAviso() {
    this.elAviso.textContent = '';
    this.elAviso.hidden = true;
  }
}

/** Un elemento con su clase. */
function elemento(etiqueta, clase) {
  const nodo = document.createElement(etiqueta);
  nodo.className = clase;
  return nodo;
}

/** El ícono de una imagen. Un SVG se arma con createElementNS, en su propio espacio de nombres. */
function iconoImagen() {
  const SVG = 'http://www.w3.org/2000/svg';
  const crear = (etiqueta, atributos) => {
    const nodo = document.createElementNS(SVG, etiqueta);
    for (const [nombre, valor] of Object.entries(atributos)) {
      nodo.setAttribute(nombre, valor);
    }
    return nodo;
  };

  const svg = crear('svg', {
    width: '40', height: '40', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
    'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
  });
  svg.append(
    crear('rect', { x: '3', y: '3', width: '18', height: '18', rx: '2', ry: '2' }),
    crear('circle', { cx: '8.5', cy: '8.5', r: '1.5' }),
    crear('polyline', { points: '21 15 16 10 5 21' }),
  );
  return svg;
}
