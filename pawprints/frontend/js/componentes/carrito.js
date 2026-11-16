/**
 * Carrito de compras (TP5-11). Un componente propio, como los del TP4, que se carga en todas las
 * páginas desde base.html.twig.
 *
 * - Guarda los libros elegidos en Local Storage, bajo la clave «pawprints.carrito», como un
 *   arreglo JSON de { id, titulo, autor, precio, imagen, stock, cantidad }. Sobrevive a recargar
 *   la página y a cerrar el navegador, y se comparte entre pestañas (evento «storage»).
 * - Cualquier elemento con data-carrito-agregar (el «Agregar al carrito» de la ficha y de cada
 *   tarjeta del catálogo) agrega su libro, que viaja en sus data-*. El listener va en el
 *   documento, así también anda en las tarjetas que redibuja el filtro del catálogo. Esos
 *   elementos son enlaces a /reserva?libro=N: sin JavaScript, llevan a reservar ese libro solo.
 * - Dibuja el panel #carrito (cada libro con su cantidad, el subtotal y «Quitar», el total,
 *   «Reservar» y «Vaciar») y el contador del «Carrito» del encabezado.
 * - El panel se abre como un costado de la pantalla (un <dialog> modal): con el «Carrito» del
 *   encabezado, al agregar un libro y al entrar con #carrito en la dirección. Sin JavaScript
 *   queda como en el TP1, al pie de la página, y el «Carrito» es un enlace hasta él.
 * - «Reservar» lleva a /reserva?libros[3]=2&libros[7]=1, que el servidor convierte en un renglón
 *   por libro. Al confirmarse la reserva, la página de confirmación trae en data-reservados los
 *   libros reservados, y salen del carrito.
 *
 * El sitio no cobra: el carrito arma una reserva, que se paga al retirarla en la tienda. Los
 * precios del carrito son los que había al agregar cada libro; el formulario de reserva muestra
 * los del catálogo y el servidor vuelve a validar todo (stock, topes, que el libro exista).
 *
 * Todo el HTML se arma con createElement y textContent, nunca con innerHTML: el título de un libro
 * es texto, aunque lo haya escrito alguien del personal (ver XSS, TP5 consigna 6).
 */
import { pesos } from './filtros-catalogo.js';

export const CLAVE_CARRITO = 'pawprints.carrito';

/** Los mismos topes que Models/Reserva: libros distintos y ejemplares de cada uno. */
export const MAX_LIBROS = 10;
export const MAX_EJEMPLARES = 5;

/** Cuántos ejemplares de un libro se pueden reservar: el tope, o lo que queda si es menos. */
export function tope(libro) {
  return Number.isInteger(libro.stock) ? Math.min(MAX_EJEMPLARES, libro.stock) : MAX_EJEMPLARES;
}

/**
 * Lee el carrito de Local Storage. Lo que no tenga la forma esperada se descarta: un JSON roto,
 * un renglón sin id o una cantidad imposible no rompen la página, y el almacenamiento bloqueado
 * da un carrito vacío.
 *
 * @returns {Array<{id: number, titulo: string, autor: string, precio: number, imagen: string, stock: number|null, cantidad: number}>}
 */
export function leerCarrito() {
  let lista;
  try {
    lista = JSON.parse(localStorage.getItem(CLAVE_CARRITO) ?? '[]');
  } catch {
    return [];
  }
  if (!Array.isArray(lista)) return [];

  const vistos = new Set();
  return lista
    .map(normalizarLibro)
    .filter((libro) => libro !== null && !vistos.has(libro.id) && vistos.add(libro.id))
    .map((libro) => ({ ...libro, cantidad: Math.min(libro.cantidad, tope(libro)) }))
    .filter((libro) => libro.cantidad > 0)
    .slice(0, MAX_LIBROS);
}

/** Guarda el carrito. Si Local Storage no está disponible, el carrito dura lo que la página. */
export function guardarCarrito(libros) {
  try {
    localStorage.setItem(CLAVE_CARRITO, JSON.stringify(libros));
  } catch {
    // Sin almacenamiento el sitio sigue andando: se puede reservar desde la ficha.
  }
  return libros;
}

/**
 * Suma un ejemplar de un libro (o lo agrega con uno). Devuelve el carrito y qué pasó, para el
 * aviso: «agregado», «tope» si ya tiene todos los que se pueden, o «lleno» si ya hay
 * MAX_LIBROS libros distintos.
 */
export function agregar(datos) {
  const libro = normalizarLibro({ ...datos, cantidad: 1 });
  const libros = leerCarrito();
  if (libro === null) return { libros, resultado: 'invalido' };

  const actual = libros.find((l) => l.id === libro.id);
  if (actual) {
    // Los datos del libro se actualizan con los de la página, que son los más nuevos.
    const nuevo = { ...actual, ...libro, cantidad: actual.cantidad };
    if (nuevo.cantidad >= tope(nuevo)) return { libros, resultado: 'tope', libro: nuevo };
    nuevo.cantidad += 1;
    return { libros: guardarCarrito(libros.map((l) => (l.id === libro.id ? nuevo : l))), resultado: 'agregado', libro: nuevo };
  }
  if (libros.length >= MAX_LIBROS) return { libros, resultado: 'lleno', libro };
  if (tope(libro) < 1) return { libros, resultado: 'tope', libro };
  return { libros: guardarCarrito([...libros, libro]), resultado: 'agregado', libro };
}

/** Cambia la cantidad de un libro, dentro de 1 y su tope. */
export function cambiarCantidad(id, cantidad) {
  return guardarCarrito(leerCarrito().map((l) => (
    l.id === id ? { ...l, cantidad: Math.max(1, Math.min(tope(l), cantidad)) } : l
  )));
}

export function quitar(id) {
  return guardarCarrito(leerCarrito().filter((l) => l.id !== id));
}

export function vaciar() {
  return guardarCarrito([]);
}

/** Saca los libros que se acaban de reservar. */
export function sacarReservados(ids) {
  const reservados = new Set(ids);
  return guardarCarrito(leerCarrito().filter((l) => !reservados.has(l.id)));
}

/** La dirección del formulario de reserva con todo el carrito: /reserva?libros[3]=2&libros[7]=1. */
export function enlaceDeReserva(libros) {
  const query = libros.map((l) => `${encodeURIComponent(`libros[${l.id}]`)}=${l.cantidad}`).join('&');
  return `/reserva?${query}`;
}

export function total(libros) {
  return libros.reduce((suma, l) => suma + l.precio * l.cantidad, 0);
}

export function ejemplares(libros) {
  return libros.reduce((suma, l) => suma + l.cantidad, 0);
}

/** Un libro con los tipos correctos, o null si le falta algo que hace falta para mostrarlo. */
function normalizarLibro(crudo) {
  if (typeof crudo !== 'object' || crudo === null) return null;
  const id = Number(crudo.id);
  const precio = Number(crudo.precio);
  const cantidad = Number(crudo.cantidad);
  const stock = crudo.stock === null || crudo.stock === undefined || crudo.stock === '' ? null : Number(crudo.stock);
  if (!Number.isInteger(id) || id < 1 || !Number.isInteger(precio) || precio < 0) return null;
  if (typeof crudo.titulo !== 'string' || crudo.titulo.trim() === '') return null;
  return {
    id,
    titulo: crudo.titulo,
    autor: typeof crudo.autor === 'string' ? crudo.autor : '',
    precio,
    // Sólo un nombre de archivo de img/: una ruta o una URL de otro sitio no se usan.
    imagen: typeof crudo.imagen === 'string' && /^[\w./-]+$/.test(crudo.imagen) && !crudo.imagen.includes('..')
      ? crudo.imagen : 'sin-tapa.svg',
    stock: Number.isInteger(stock) && stock >= 0 ? stock : null,
    cantidad: Number.isInteger(cantidad) && cantidad > 0 ? cantidad : 1,
  };
}

/** Los datos del libro que lleva un «Agregar al carrito» en sus data-*. */
export function libroDe(elemento) {
  const d = elemento.dataset;
  return { id: d.libroId, titulo: d.titulo, autor: d.autor, precio: d.precio, imagen: d.imagen, stock: d.stock };
}

/**
 * El panel #carrito y el contador del encabezado.
 *
 * Uso: new PanelCarrito(document.getElementById('carrito'), enlaceDelEncabezado)
 *
 * El <aside> que imprime el servidor al pie se mueve adentro de un <dialog>, que se abre con
 * showModal(): el navegador oscurece el resto, lo vuelve inerte, deja el foco adentro y lo cierra
 * con Esc, sin código propio para cada cosa.
 */
export class PanelCarrito {
  constructor(panel, enlace) {
    this.panel = panel;
    this.enlace = enlace;
    this.vacio = panel.querySelector('p');
    this.armarDialogo();
    this.armarVacio();

    this.contenido = document.createElement('div');
    this.contenido.dataset.carritoContenido = '';
    // El aviso de cada cambio para el lector de pantalla: el panel suele estar fuera de la vista.
    this.aviso = Object.assign(document.createElement('p'), { className: 'solo-lectores' });
    this.aviso.setAttribute('role', 'status');
    this.panel.append(this.contenido, this.aviso);

    if (this.enlace) {
      this.contador = document.createElement('span');
      this.contador.className = 'contador-carrito';
      this.enlace.append(this.contador);
    }

    document.addEventListener('click', (e) => this.alHacerClic(e));
    // Un enlace a #carrito (el del encabezado sin JavaScript, o uno guardado) abre el panel.
    window.addEventListener('hashchange', () => {
      if (window.location.hash === '#carrito') this.abrir();
    });
    // Otra pestaña cambió el carrito: se redibuja con lo nuevo.
    window.addEventListener('storage', (e) => {
      if (e.key === CLAVE_CARRITO || e.key === null) this.dibujar();
    });

    this.dibujar();
    if (window.location.hash === '#carrito') this.abrir();
  }

  armarDialogo() {
    this.dialogo = document.createElement('dialog');
    this.dialogo.className = 'carrito-dialogo';
    const titulo = this.panel.querySelector('h2');
    titulo.id ||= 'titulo-carrito';
    this.dialogo.setAttribute('aria-labelledby', titulo.id);

    // El título y la cruz para cerrar, en una misma fila.
    const cerrar = boton('×', { carritoAccion: 'cerrar' }, 'Cerrar el carrito');
    cerrar.className = 'carrito-cerrar';
    const cabecera = elemento('div', { className: 'carrito-cabecera' });
    titulo.before(cabecera);
    cabecera.append(titulo, cerrar);

    this.panel.replaceWith(this.dialogo);
    this.dialogo.append(this.panel);

    // Tocar el fondo oscuro cierra: ese clic le llega al <dialog> mismo, porque el panel lo llena.
    this.dialogo.addEventListener('click', (e) => {
      if (e.target === this.dialogo) this.cerrar();
    });
    // Con Esc lo cierra el navegador: acá sólo se devuelve el foco y se limpia la dirección.
    this.dialogo.addEventListener('close', () => this.alCerrar());

    if (this.enlace) {
      this.enlace.setAttribute('role', 'button');
      this.enlace.setAttribute('aria-haspopup', 'dialog');
      this.enlace.setAttribute('aria-controls', this.panel.id);
      this.enlace.setAttribute('aria-expanded', 'false');
      // Como botón, también se activa con la barra espaciadora.
      this.enlace.addEventListener('keydown', (e) => {
        if (e.key === ' ') {
          e.preventDefault();
          this.abrir();
        }
      });
    }
  }

  /** Vacío, el panel invita a ir al catálogo: «El carrito está vacío.» parecía el maquetado del TP1. */
  armarVacio() {
    this.vacio.replaceChildren(
      'Todavía no agregaste libros. Usá «Agregar al carrito» en el catálogo o en la ficha de un libro. ',
      elemento('a', { href: '/catalogo' }, 'Ver el catálogo →'),
    );
  }

  abrir() {
    if (this.dialogo.open) return;
    this.anterior = document.activeElement;
    this.dialogo.showModal();
    this.enlace?.setAttribute('aria-expanded', 'true');
  }

  cerrar() {
    if (this.dialogo.open) this.dialogo.close();
  }

  alCerrar() {
    this.enlace?.setAttribute('aria-expanded', 'false');
    // Sin el #carrito en la dirección, recargar no lo vuelve a abrir.
    if (window.location.hash === '#carrito') {
      history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    if (this.anterior instanceof HTMLElement && this.anterior.isConnected) this.anterior.focus();
  }

  alHacerClic(e) {
    const abrir = e.target.closest('a[href="#carrito"]');
    if (abrir && !this.panel.contains(abrir)) {
      e.preventDefault();
      this.abrir();
      return;
    }

    const boton = e.target.closest('[data-carrito-agregar]');
    if (boton) {
      // El enlace a /reserva queda para cuando no hay JavaScript.
      e.preventDefault();
      this.alAgregar(boton);
      return;
    }

    const accion = e.target.closest('[data-carrito-accion]');
    if (!accion || !this.panel.contains(accion)) return;
    const id = Number(accion.dataset.libroId);
    const libro = leerCarrito().find((l) => l.id === id);

    switch (accion.dataset.carritoAccion) {
      case 'menos':
        if (libro) this.cambiar(cambiarCantidad(id, libro.cantidad - 1), `«${libro.titulo}»: ${Math.max(1, libro.cantidad - 1)}.`);
        break;
      case 'mas':
        if (libro) this.cambiar(cambiarCantidad(id, libro.cantidad + 1), `«${libro.titulo}»: ${Math.min(tope(libro), libro.cantidad + 1)}.`);
        break;
      case 'quitar':
        if (libro) this.cambiar(quitar(id), `Quitaste «${libro.titulo}» del carrito.`, true);
        break;
      case 'vaciar':
        this.cambiar(vaciar(), 'Vaciaste el carrito.', true);
        break;
      case 'cerrar':
        this.cerrar();
        break;
      default:
    }
  }

  alAgregar(boton) {
    const { libros, resultado, libro } = agregar(libroDe(boton));
    const mensajes = {
      agregado: () => `Agregaste «${libro.titulo}» al carrito.`,
      tope: () => `Ya tenés en el carrito todos los ejemplares de «${libro.titulo}» que se pueden reservar.`,
      lleno: () => `El carrito ya tiene ${MAX_LIBROS} libros distintos, el máximo de una reserva.`,
      invalido: () => 'No se pudo agregar ese libro.',
    };
    this.dibujar(libros);
    // El panel se abre con lo que se agregó (o con el aviso de por qué no): así se ve qué pasó.
    this.abrir();
    this.avisar(mensajes[resultado]());
    this.mostrarAviso(mensajes[resultado]());
  }

  /** El aviso de agregar también se ve, arriba de la lista: el de lector de pantalla no se ve. */
  mostrarAviso(texto) {
    this.visible ??= this.panel.querySelector('.carrito-aviso')
      ?? elemento('p', { className: 'carrito-aviso' });
    this.visible.textContent = texto;
    this.panel.querySelector('.carrito-cabecera').after(this.visible);
  }

  cambiar(libros, mensaje, moverFoco = false) {
    this.dibujar(libros);
    this.visible?.remove();
    this.avisar(mensaje);
    // El botón que se tocó ya no existe: el foco vuelve al título del panel en lugar de perderse.
    if (moverFoco) {
      const titulo = this.panel.querySelector('h2');
      titulo.tabIndex = -1;
      titulo.focus();
    }
  }

  avisar(texto) {
    // Vaciar y volver a escribir hace que el lector lo anuncie aunque el texto se repita.
    this.aviso.textContent = '';
    setTimeout(() => { this.aviso.textContent = texto; }, 50);
  }

  dibujar(libros = leerCarrito()) {
    const cantidad = ejemplares(libros);
    if (this.contador) {
      this.contador.textContent = cantidad > 0 ? ` (${cantidad})` : '';
      this.enlace.setAttribute('aria-label', cantidad > 0
        ? `Carrito: ${cantidad} ${cantidad === 1 ? 'ejemplar' : 'ejemplares'}` : 'Carrito vacío');
    }

    this.vacio.hidden = libros.length > 0;
    if (libros.length === 0) {
      this.contenido.replaceChildren();
      return;
    }

    const lista = document.createElement('ul');
    lista.append(...libros.map((l) => this.renglon(l)));

    const resumen = elemento('p', { className: 'carrito-total' },
      'Total: ', elemento('strong', {}, pesos(total(libros))),
      ` · ${cantidad} ${cantidad === 1 ? 'ejemplar' : 'ejemplares'}`);

    const acciones = elemento('p', { className: 'carrito-acciones' },
      elemento('a', { href: enlaceDeReserva(libros), className: 'boton-carrito' }, 'Reservar estos libros'),
      boton('Vaciar carrito', { carritoAccion: 'vaciar' }));

    const nota = elemento('p', { className: 'carrito-nota' },
      'Se reserva sin pagar y se paga al retirar en la tienda.');

    this.contenido.replaceChildren(lista, resumen, acciones, nota);
  }

  renglon(libro) {
    const maximo = tope(libro);
    const imagen = elemento('img', { src: `/img/${libro.imagen}`, alt: '', width: 48, height: 64 });
    const datos = elemento('div', {},
      elemento('h3', {}, elemento('a', { href: `/libros/${libro.id}` }, libro.titulo)),
      elemento('p', {}, `${libro.autor} · ${pesos(libro.precio)} c/u`));

    const menos = boton('−', { carritoAccion: 'menos', libroId: libro.id }, `Un ejemplar menos de «${libro.titulo}»`);
    menos.disabled = libro.cantidad <= 1;
    const mas = boton('+', { carritoAccion: 'mas', libroId: libro.id }, `Un ejemplar más de «${libro.titulo}»`);
    mas.disabled = libro.cantidad >= maximo;
    const cantidad = elemento('output', {}, String(libro.cantidad));
    cantidad.setAttribute('aria-label', `Ejemplares de «${libro.titulo}»`);
    const control = elemento('div', { className: 'carrito-cantidad' }, menos, cantidad, mas);

    const subtotal = elemento('p', { className: 'carrito-subtotal' }, pesos(libro.precio * libro.cantidad));
    const sacar = boton('Quitar', { carritoAccion: 'quitar', libroId: libro.id }, `Quitar «${libro.titulo}» del carrito`);

    return elemento('li', {}, imagen, datos, control, subtotal, sacar);
  }
}

function elemento(etiqueta, propiedades, ...hijos) {
  const nodo = Object.assign(document.createElement(etiqueta), propiedades);
  nodo.append(...hijos);
  return nodo;
}

function boton(texto, datos, etiqueta = null) {
  const nodo = elemento('button', { type: 'button' }, texto);
  Object.assign(nodo.dataset, datos);
  if (etiqueta) nodo.setAttribute('aria-label', etiqueta);
  return nodo;
}

// Arranque, en todas las páginas: el panel del pie y el enlace del encabezado.
const panel = document.getElementById('carrito');
if (panel) {
  // La confirmación de una reserva dice qué libros se reservaron: salen del carrito antes de
  // dibujarlo.
  const reservados = document.querySelector('main[data-reservados]')?.dataset.reservados ?? '';
  if (reservados !== '') {
    sacarReservados(reservados.split(',').map(Number));
  }
  new PanelCarrito(panel, document.querySelector('body > header a[href="#carrito"]'));
}
