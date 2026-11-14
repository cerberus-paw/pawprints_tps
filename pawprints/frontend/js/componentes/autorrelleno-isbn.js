/**
 * Autorrelleno de la carga de libros con los datos de Open Library (TP5, consigna 7).
 *
 * Al salir del campo ISBN con un valor válido, o con el botón «Buscar datos», le pide a
 * /libros/isbn/{isbn} el título, el autor, la descripción y la tapa, y completa los campos que
 * siguen vacíos: lo que el personal ya escribió no se pisa. El navegador no le habla a Open
 * Library directamente; pasa por el backend, así la CSP no abre connect-src a otro dominio.
 *
 * Uso: new AutorrellenoIsbn(campoIsbn, { esValido, zonaTapa })
 *   esValido(valor) — si vale la pena buscar ese ISBN (formato y dígito verificador)
 *   zonaTapa        — la ZonaArrastre de la tapa, para mostrar la de Open Library
 *
 * Sin JavaScript no aparece nada de esto y el formulario anda como en el TP4: el servidor busca
 * la tapa igual al guardar.
 */

const CAMPOS = { titulo: 'el título', autor: 'el autor', descripcion: 'la descripción' };

export class AutorrellenoIsbn {
  constructor(campo, { esValido = () => true, zonaTapa = null } = {}) {
    if (!(campo instanceof HTMLInputElement) || !campo.form) {
      console.warn('AutorrellenoIsbn: no se encontró el campo ISBN; no se arma nada.');
      return;
    }

    this.campo = campo;
    this.formulario = campo.form;
    this.esValido = esValido;
    this.zonaTapa = zonaTapa;
    this.url = campo.dataset.autorrelleno ?? '/libros/isbn/';
    // El último ISBN buscado: salir del campo sin cambiarlo no lo vuelve a pedir.
    this.buscado = null;
    this.pedido = null;

    this.armar();
    this.escuchar();
  }

  armar() {
    // El campo y el botón en una fila. El botón lo agrega el JS: sin él no haría nada.
    const fila = document.createElement('div');
    fila.className = 'isbn-con-busqueda';
    this.campo.before(fila);

    this.boton = document.createElement('button');
    this.boton.type = 'button';
    this.boton.className = 'btn-outline';
    this.boton.textContent = 'Buscar datos';
    this.boton.setAttribute('aria-describedby', 'estado-autorrelleno');
    fila.append(this.campo, this.boton);

    // Una región que ya existe, vacía, antes de escribir en ella: así el lector de pantalla
    // anuncia cada mensaje. No va con hidden, que la sacaría del árbol de accesibilidad; vacía,
    // el CSS no le da lugar.
    this.estado = document.createElement('p');
    this.estado.id = 'estado-autorrelleno';
    this.estado.className = 'estado-autorrelleno';
    this.estado.setAttribute('role', 'status');
    this.estado.setAttribute('aria-live', 'polite');
    const ayuda = this.campo.closest('.campo')?.querySelector('small');
    (ayuda ?? fila).after(this.estado);
  }

  escuchar() {
    // change llega al salir del campo, y sólo si el valor cambió.
    this.campo.addEventListener('change', () => {
      if (this.esValido(this.campo.value.trim())) this.buscar();
    });

    this.boton.addEventListener('click', () => {
      const valor = this.campo.value.trim();
      if (!this.esValido(valor)) {
        // El mensaje de por qué no es válido lo muestra el validador al salir del campo.
        this.campo.focus();
        this.avisar('Escribí un ISBN válido para buscar sus datos.', 'error');
        return;
      }
      this.buscar(true);
    });

    // Enter en el ISBN busca en lugar de mandar el formulario a medio completar.
    this.campo.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.boton.click();
      }
    });
  }

  /** @param {boolean} otraVez buscar aunque sea el mismo ISBN de la última vez */
  async buscar(otraVez = false) {
    const digitos = this.campo.value.replace(/\D/g, '');
    if (!otraVez && digitos === this.buscado) return;
    this.buscado = digitos;

    // Si cambió el ISBN mientras se buscaba el anterior, la respuesta vieja ya no sirve.
    this.pedido?.abort();
    const pedido = new AbortController();
    this.pedido = pedido;

    this.boton.disabled = true;
    this.formulario.setAttribute('aria-busy', 'true');
    this.avisar('Buscando los datos del libro en Open Library…');

    try {
      const respuesta = await fetch(this.url + encodeURIComponent(digitos), {
        headers: { Accept: 'application/json' },
        signal: pedido.signal,
      });
      const datos = await respuesta.json().catch(() => ({}));
      this.mostrar(respuesta.status, datos);
    } catch (error) {
      if (error.name === 'AbortError') return;
      this.buscado = null;
      this.avisar('No pudimos buscar los datos. Revisá la conexión, o completalos a mano.', 'error');
    } finally {
      if (this.pedido === pedido) {
        this.pedido = null;
        this.boton.disabled = false;
        this.formulario.removeAttribute('aria-busy');
      }
    }
  }

  mostrar(estado, datos) {
    const repetido = datos.catalogo ? `Ojo: «${datos.catalogo.titulo}» ya está en el catálogo con ese ISBN.` : '';

    if (estado !== 200) {
      // 503 y errores de red: se puede volver a probar con el mismo ISBN.
      if (estado >= 500) this.buscado = null;
      this.zonaTapa?.olvidarSugerida();
      const mensaje = datos.error ?? 'No pudimos buscar los datos. Completalos a mano.';
      this.avisar([repetido, mensaje].filter(Boolean).join(' '), 'error');
      return;
    }

    const completados = [];
    for (const [nombre, texto] of Object.entries(CAMPOS)) {
      const control = this.formulario.elements.namedItem(nombre);
      if (!datos[nombre] || !control || control.value.trim() !== '') continue;
      control.value = datos[nombre];
      // Para que el validador revise el campo y saque el error de «obligatorio» si lo había.
      control.dispatchEvent(new Event('input', { bubbles: true }));
      completados.push(texto);
    }

    if (datos.tapa) this.zonaTapa?.sugerir(datos.tapa, 'Tapa de Open Library');
    else this.zonaTapa?.olvidarSugerida();

    const partes = [];
    if (completados.length) {
      partes.push(`Completamos ${lista(completados)} con los datos de Open Library.`);
    } else {
      partes.push('Open Library tiene el libro, pero los campos que tiene ya estaban completos: no cambiamos nada.');
    }
    if (datos.tapa) partes.push('Si no elegís otra tapa, se guarda la suya.');
    partes.push('Revisá que estén bien antes de cargarlo.');
    if (repetido) partes.unshift(repetido);

    this.avisar(partes.join(' '), repetido ? 'error' : 'ok');
  }

  /** @param {'info'|'ok'|'error'} tipo */
  avisar(mensaje, tipo = 'info') {
    this.estado.textContent = mensaje;
    this.estado.dataset.tipo = tipo;
  }
}

/** «el título, el autor y la descripción» */
function lista(partes) {
  return partes.length === 1 ? partes[0] : `${partes.slice(0, -1).join(', ')} y ${partes.at(-1)}`;
}
