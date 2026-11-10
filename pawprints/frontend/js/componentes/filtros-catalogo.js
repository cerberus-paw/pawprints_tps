/**
 * Filtros del catálogo en el navegador (TP4, consigna 4). Trae el catálogo entero una sola vez
 * desde /catalogo/json y, desde ahí, busca, filtra, ordena y pagina sin recargar la página.
 *
 * Uso: new FiltrosCatalogo(main)
 *
 * El contenedor es el <main> del catálogo, con data-catalogo-json apuntando a los datos. El
 * resto lo encuentra por id: los formularios #catalogo-busqueda, #catalogo-filtros y
 * #catalogo-vista, el resumen #catalogo-resumen, el link #catalogo-csv y la tarjeta vacía del
 * <template id="plantilla-libro">.
 *
 * Mejora progresiva: el servidor ya imprimió la página correcta para la URL, y sin JS sigue
 * funcionando con recargas. El componente toma el control recién cuando el JSON llega bien; si
 * el pedido falla, la página sigue como estaba.
 *
 * La vista actual vive en la URL con los mismos parámetros que entiende el servidor, así
 * recargar o compartir el link muestra lo mismo.
 */

/** Los parámetros de la vista actual, en el orden en que los escribe el servidor. */
const FILTROS = ['q', 'genero', 'formato', 'precio_desde', 'precio_hasta', 'orden', 'por_pagina'];

/** Las claves de CatalogoController::ORDENES; otro valor cae en el orden por defecto. */
const ORDENES = [
  'recomendados',
  'titulo-asc', 'titulo-desc',
  'autor-asc', 'autor-desc',
  'precio-asc', 'precio-desc',
];

/** Los mismos de LibroRepository::CANTIDADES; el primero es el de por defecto. */
const CANTIDADES = [4, 8, 12, 24];

const ORDEN_POR_DEFECTO = 'recomendados';

/** Cuánto se espera a que la persona deje de escribir antes de filtrar, en milisegundos. */
const ESPERA = 300;

/** Los valores que no hace falta escribir en la URL, porque son los de por defecto. */
const POR_DEFECTO = { orden: ORDEN_POR_DEFECTO, por_pagina: String(CANTIDADES[0]), pagina: '1' };

/** Las mismas letras que reemplaza LibroRepository::normalizar(). */
const SIN_TILDES = {
  á: 'a', é: 'e', í: 'i', ó: 'o', ú: 'u', ü: 'u', ñ: 'n',
  Á: 'a', É: 'e', Í: 'i', Ó: 'o', Ú: 'u', Ü: 'u', Ñ: 'n',
};

/** Sin tildes y en minúsculas, igual que el servidor: «ficcion» encuentra «Ficción». */
export function normalizar(texto) {
  return texto.trim().replace(/[áéíóúüñÁÉÍÓÚÜÑ]/g, (letra) => SIN_TILDES[letra]).toLowerCase();
}

/** Un monto en pesos, o null si está vacío o no es un entero no negativo («sin límite»). */
export function monto(valor) {
  const texto = String(valor ?? '').trim();
  return /^\d+$/.test(texto) ? Number(texto) : null;
}

/** El rango de precio pedido, o null si el mínimo supera al máximo y no se puede aplicar. */
export function rangoDePrecio(estado) {
  const minimo = monto(estado.precio_desde);
  const maximo = monto(estado.precio_hasta);
  if (minimo !== null && maximo !== null && minimo > maximo) {
    return null;
  }
  return { minimo, maximo };
}

/** $16.500, como LibroRepository: punto de miles y sin decimales. */
export function pesos(monto) {
  return '$' + String(monto).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Todo lo que mira la búsqueda de un libro, como LibroRepository::indice(). */
function indice(libro) {
  return normalizar([
    libro.titulo, libro.autor, libro.genero, libro.isbn, libro.isbn.replaceAll('-', ''),
  ].join(' '));
}

/** Los libros que coinciden con la búsqueda y los filtros, como LibroRepository::buscar(). */
export function filtrar(libros, estado) {
  const buscado = normalizar(estado.q);
  const rango = rangoDePrecio(estado) ?? { minimo: null, maximo: null };

  return libros.filter((libro) =>
    (buscado === '' || indice(libro).includes(buscado))
    && (estado.genero === '' || libro.genero === estado.genero)
    && (estado.formato === '' || libro.formato === estado.formato)
    && (rango.minimo === null || libro.precio >= rango.minimo)
    && (rango.maximo === null || libro.precio <= rango.maximo));
}

/**
 * Ordena por título, autor o precio, ascendente (-asc) o descendente (-desc). Los textos se
 * comparan sin que las tildes ni las mayúsculas cambien el orden. sort es estable: los empates
 * quedan en el orden del catálogo. Un orden desconocido deja el orden del catálogo.
 */
export function ordenar(libros, orden) {
  const [campo, sentido] = orden.split('-');
  const signo = { asc: 1, desc: -1 }[sentido];
  const comparar = {
    titulo: (a, b) => a.titulo.localeCompare(b.titulo, 'es', { sensitivity: 'base' }),
    autor: (a, b) => a.autor.localeCompare(b.autor, 'es', { sensitivity: 'base' }),
    precio: (a, b) => a.precio - b.precio,
  }[campo];

  if (signo === undefined || comparar === undefined) {
    return [...libros];
  }
  return [...libros].sort((a, b) => signo * comparar(a, b));
}

/** La vista actual a partir de una query, con los mismos valores por defecto que el servidor. */
export function estadoDesde(query) {
  const params = new URLSearchParams(query);
  const estado = {};
  for (const nombre of FILTROS) {
    estado[nombre] = (params.get(nombre) ?? '').trim();
  }

  const porPagina = Number(estado.por_pagina);
  estado.por_pagina = CANTIDADES.includes(porPagina) ? porPagina : CANTIDADES[0];
  if (!ORDENES.includes(estado.orden)) {
    estado.orden = ORDEN_POR_DEFECTO;
  }
  const pagina = Number(params.get('pagina') ?? '1');
  estado.pagina = Number.isInteger(pagina) && pagina >= 1 ? pagina : 1;
  return estado;
}

/**
 * La query de una vista, como la arma el servidor: los filtros en el orden de FILTROS y la
 * página al final, sin vacíos ni valores por defecto. Con conPagina en false queda afuera la
 * página, como en el link del CSV.
 */
export function queryDe(estado, { conPagina = true } = {}) {
  const params = new URLSearchParams();
  const nombres = conPagina ? [...FILTROS, 'pagina'] : FILTROS;
  for (const nombre of nombres) {
    const valor = String(estado[nombre] ?? '');
    if (valor !== '' && valor !== POR_DEFECTO[nombre]) {
      params.append(nombre, valor);
    }
  }
  const query = params.toString();
  return query === '' ? '' : `?${query}`;
}

/**
 * Una función que corre recién cuando dejan de llamarla durante `ms`; con .ya() corre al toque.
 */
function conEspera(funcion, ms) {
  let temporizador;
  const demorada = () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(funcion, ms);
  };
  demorada.ya = () => {
    clearTimeout(temporizador);
    funcion();
  };
  return demorada;
}

export class FiltrosCatalogo {
  constructor(contenedor) {
    // Sin contenedor no se arma nada, y la página sigue andando con el servidor.
    if (!(contenedor instanceof HTMLElement) || !contenedor.dataset.catalogoJson) {
      console.warn('FiltrosCatalogo: no se encontró el catálogo; no se arma nada.');
      return;
    }
    this.contenedor = contenedor;
    this.busqueda = document.getElementById('catalogo-busqueda');
    this.filtros = document.getElementById('catalogo-filtros');
    this.vista = document.getElementById('catalogo-vista');
    this.resultados = this.vista?.closest('section');
    this.titulo = document.getElementById('titulo-lista-libros');
    this.resumen = document.getElementById('catalogo-resumen');
    this.csv = document.getElementById('catalogo-csv');
    this.plantilla = document.getElementById('plantilla-libro');

    if (!this.busqueda || !this.filtros || !this.resultados || !this.resumen || !this.plantilla) {
      console.warn('FiltrosCatalogo: falta parte del catálogo; no se arma nada.');
      return;
    }

    this.estado = estadoDesde(window.location.search);
    this.plegarFiltros();
    this.cargar(contenedor.dataset.catalogoJson);
  }

  /**
   * En pantallas chicas los filtros arrancan plegados detrás de un botón «Filtros». Cuándo se
   * pliegan lo decide el CSS (900 px o menos, cuando el panel se apila sobre el listado): en
   * pantallas grandes el botón no se ve y los filtros siempre están abiertos. No depende de que
   * llegue el JSON: plegar sirve aunque los filtros sigan recargando la página.
   */
  plegarFiltros() {
    const panel = this.filtros.closest('aside');
    if (!panel) {
      return;
    }
    const boton = Object.assign(document.createElement('button'), {
      type: 'button',
      className: 'boton-filtros',
      textContent: 'Filtros',
    });
    boton.setAttribute('aria-controls', this.filtros.id);
    boton.setAttribute('aria-expanded', 'false');
    panel.dataset.plegado = '';
    boton.addEventListener('click', () => {
      const abrir = boton.getAttribute('aria-expanded') === 'false';
      boton.setAttribute('aria-expanded', String(abrir));
      panel.toggleAttribute('data-plegado', !abrir);
    });
    this.filtros.before(boton);
  }

  /** Trae el catálogo una sola vez. Si algo falla, la página queda como la dejó el servidor. */
  async cargar(url) {
    try {
      const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
      if (!respuesta.ok) {
        throw new Error(`el servidor respondió ${respuesta.status}`);
      }
      this.libros = await respuesta.json();
    } catch (error) {
      console.warn('FiltrosCatalogo: no se pudo traer el catálogo; sigue con recargas.', error);
      return;
    }
    this.tomarControl();
    this.dibujar();
  }

  /** Engancha los controles: desde acá, ningún formulario del catálogo recarga la página. */
  tomarControl() {
    this.controlarBusqueda();
    this.controlarFiltros();
    this.controlarPrecio();

    const orden = this.vista.elements.namedItem('orden');
    const porPagina = this.vista.elements.namedItem('por_pagina');

    orden.addEventListener('change', () => this.cambiar({ orden: orden.value }));
    porPagina.addEventListener('change', () => {
      this.cambiar({ por_pagina: Number(porPagina.value) });
    });
    this.vista.addEventListener('submit', (evento) => evento.preventDefault());
    this.ocultarBoton(this.vista);

    // Los links del paginado son links de verdad (sirven para abrir en otra pestaña); un clic
    // común cambia de página acá.
    this.resultados.addEventListener('click', (evento) => {
      const enlace = evento.target.closest('nav[aria-label="Paginado del catálogo"] a');
      const conTecla = evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey;
      if (!enlace || evento.button !== 0 || conTecla) {
        return;
      }
      evento.preventDefault();
      this.irAPagina(estadoDesde(new URL(enlace.href).search).pagina);
    });
  }

  /** La búsqueda filtra mientras se escribe; Enter (o «Buscar») aplica sin esperar. */
  controlarBusqueda() {
    const campo = this.busqueda.elements.namedItem('q');
    const buscar = conEspera(() => this.cambiar({ q: campo.value.trim() }), ESPERA);
    campo.addEventListener('input', buscar);
    this.busqueda.addEventListener('submit', (evento) => {
      evento.preventDefault();
      buscar.ya();
    });
  }

  /** Género y formato se aplican apenas se elige otra opción. */
  controlarFiltros() {
    this.filtros.addEventListener('change', (evento) => {
      const { name, value } = evento.target;
      if (name === 'genero' || name === 'formato') {
        this.cambiar({ [name]: value });
      }
    });
    this.filtros.addEventListener('submit', (evento) => evento.preventDefault());
    this.ocultarBoton(this.filtros);
  }

  /**
   * «Desde» y «Hasta» filtran mientras se escribe. Si el mínimo supera al máximo, un aviso junto
   * a los campos lo dice y el precio no filtra hasta que se corrija, igual que en el servidor.
   */
  controlarPrecio() {
    this.desde = this.filtros.elements.namedItem('precio_desde');
    this.hasta = this.filtros.elements.namedItem('precio_hasta');
    if (!this.desde || !this.hasta) {
      return;
    }

    this.avisoPrecio = Object.assign(document.createElement('p'), {
      id: 'precio-aviso',
      hidden: true,
      textContent: '«Desde» no puede ser mayor que «Hasta»: el filtro de precio no se aplica.',
    });
    this.avisoPrecio.classList.add('rango-precio-aviso');
    this.desde.closest('.rango-precio').after(this.avisoPrecio);
    for (const campo of [this.desde, this.hasta]) {
      campo.setAttribute('aria-describedby', this.avisoPrecio.id);
    }

    const aplicar = conEspera(() => {
      this.cambiar({
        precio_desde: this.desde.value.trim(),
        precio_hasta: this.hasta.value.trim(),
      });
    }, ESPERA);
    this.desde.addEventListener('input', aplicar);
    this.hasta.addEventListener('input', aplicar);
  }

  /** Muestra u oculta el aviso del rango según la vista actual. */
  validarPrecio() {
    if (!this.avisoPrecio) {
      return;
    }
    const invalido = rangoDePrecio(this.estado) === null;
    this.avisoPrecio.hidden = !invalido;
    for (const campo of [this.desde, this.hasta]) {
      campo.toggleAttribute('aria-invalid', invalido);
    }
  }

  ocultarBoton(formulario) {
    for (const boton of formulario.querySelectorAll('button[type="submit"]')) {
      boton.hidden = true;
    }
  }

  /**
   * Cambiar cualquier parte de la vista vuelve a la página 1: la de antes ya no es la misma. Si
   * nada cambió de verdad (un espacio al final de la búsqueda, por ejemplo), no se toca nada.
   */
  cambiar(cambios) {
    if (Object.entries(cambios).every(([nombre, valor]) => this.estado[nombre] === valor)) {
      return;
    }
    Object.assign(this.estado, cambios, { pagina: 1 });
    this.dibujar();
  }

  irAPagina(pagina) {
    this.estado.pagina = pagina;
    this.dibujar();
    // Al cambiar de página se empieza a leer desde arriba de los resultados.
    this.resultados.scrollIntoView({ block: 'start' });
    this.titulo?.focus({ preventScroll: true });
  }

  /** Filtra, ordena, pagina y redibuja todo lo que depende de la vista actual. */
  dibujar() {
    const encontrados = ordenar(filtrar(this.libros, this.estado), this.estado.orden);
    const total = encontrados.length;
    const porPagina = this.estado.por_pagina;
    const paginas = Math.max(1, Math.ceil(total / porPagina));
    this.estado.pagina = Math.min(this.estado.pagina, paginas);

    const inicio = (this.estado.pagina - 1) * porPagina;
    const pagina = encontrados.slice(inicio, inicio + porPagina);

    this.validarPrecio();
    this.dibujarResumen(total, inicio + 1, inicio + pagina.length);
    this.dibujarListado(pagina, paginas);
    this.actualizarUrl();
  }

  /**
   * «Mostrando X–Y de Z libros», igual que el servidor. Si el texto no cambió no se toca: el
   * resumen es una región aria-live y reescribirlo igual lo volvería a anunciar.
   */
  dibujarResumen(total, primero, ultimo) {
    const libros = `${total} ${total === 1 ? 'libro' : 'libros'}`;
    const partes = total === 0
      ? ['Mostrando ', [libros]]
      : ['Mostrando ', [`${primero}–${ultimo}`], ' de ', [libros]];

    const texto = partes.flat().join('');
    if (this.resumen.textContent.replace(/\s+/g, ' ').trim() === texto) {
      return;
    }
    // Los tramos entre corchetes van en <strong>.
    this.resumen.replaceChildren(...partes.map((parte) => (Array.isArray(parte)
      ? Object.assign(document.createElement('strong'), { textContent: parte[0] })
      : parte)));
  }

  /** Reemplaza las tarjetas, el paginado o el aviso de «no encontramos» por los de la vista. */
  dibujarListado(libros, paginas) {
    for (const viejo of this.resultados.querySelectorAll(
      ':scope > ul, :scope > nav, :scope > p[data-sin-resultados]',
    )) {
      viejo.remove();
    }

    // Todo va antes del link del CSV, que es el último párrafo de los resultados.
    const ancla = this.csv?.closest('p') ?? null;
    const agregar = (elemento) => this.resultados.insertBefore(elemento, ancla);

    if (libros.length === 0) {
      agregar(this.sinResultados());
      return;
    }

    const lista = document.createElement('ul');
    lista.append(...libros.map((libro) => this.tarjeta(libro)));
    agregar(lista);

    if (paginas > 1) {
      agregar(this.paginado(paginas));
    }
  }

  /** Una tarjeta, clonada del <template> que imprime el servidor. */
  tarjeta(libro) {
    const tarjeta = this.plantilla.content.firstElementChild.cloneNode(true);
    for (const campo of tarjeta.querySelectorAll('[data-campo]')) {
      campo.textContent = libro[campo.dataset.campo];
    }
    for (const enlace of tarjeta.querySelectorAll('a')) {
      enlace.href = `/libros/${libro.id}`;
    }
    const imagen = tarjeta.querySelector('img');
    imagen.src = `/img/${libro.imagen}`;
    imagen.alt = `Portada del libro ${libro.titulo}`;
    const precio = tarjeta.querySelector('data');
    precio.value = String(libro.precio);
    precio.textContent = pesos(libro.precio);
    return tarjeta;
  }

  sinResultados() {
    const parrafo = document.createElement('p');
    parrafo.dataset.sinResultados = '';
    const enlace = Object.assign(document.createElement('a'), {
      href: '/catalogo',
      textContent: 'Ver todo el catálogo',
    });
    parrafo.append('No encontramos libros con esa búsqueda. ', enlace, '.');
    return parrafo;
  }

  /** El mismo paginado que el servidor: anterior, los números y siguiente. */
  paginado(paginas) {
    const actual = this.estado.pagina;
    const nav = document.createElement('nav');
    // La clase lleva el estilo, que comparte con el paginado de los pedidos (componentes.css).
    nav.className = 'paginado';
    nav.setAttribute('aria-label', 'Paginado del catálogo');
    const lista = document.createElement('ul');

    const item = (pagina, texto, atributos = {}) => {
      const enlace = document.createElement('a');
      enlace.href = `/catalogo${queryDe({ ...this.estado, pagina })}`;
      enlace.textContent = texto;
      for (const [nombre, valor] of Object.entries(atributos)) {
        enlace.setAttribute(nombre, valor);
      }
      const li = document.createElement('li');
      li.append(enlace);
      lista.append(li);
    };

    if (actual > 1) {
      item(actual - 1, '← Anterior', { rel: 'prev' });
    }
    for (let n = 1; n <= paginas; n++) {
      item(n, String(n), n === actual ? { 'aria-current': 'page' } : {});
    }
    if (actual < paginas) {
      item(actual + 1, 'Siguiente →', { rel: 'next' });
    }

    nav.append(lista);
    return nav;
  }

  /** La URL y el link del CSV reflejan siempre la vista actual. */
  actualizarUrl() {
    const url = `${window.location.pathname}${queryDe(this.estado)}${window.location.hash}`;
    window.history.replaceState(null, '', url);
    if (this.csv) {
      this.csv.href = `/catalogo/csv${queryDe(this.estado, { conPagina: false })}`;
    }
  }
}
