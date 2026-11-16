/**
 * Los renglones del formulario de reserva (TP5-11): un libro con su cantidad por renglón.
 *
 * Uso: new RenglonesReserva(fieldset, resumen)
 *
 * El fieldset [data-renglones] trae los renglones que armó el servidor (uno, o los del carrito) y
 * en data-max-libros el tope de libros distintos. El componente:
 * - agrega «Agregar otro libro» y un «Quitar» en cada renglón (si hay más de uno);
 * - deshabilita en cada desplegable los libros elegidos en otro renglón, porque el servidor
 *   rechaza el mismo libro dos veces;
 * - ajusta el máximo de la cantidad al stock del libro elegido;
 * - mantiene al día la tarjeta de resumen [data-resumen] (tapa, cuenta y total), que sin
 *   JavaScript muestra lo que llegó del servidor.
 *
 * Los datos de cada libro salen de los data-* de su <option>: no hace falta pedirle nada al
 * servidor. El servidor vuelve a validar todo al enviar.
 */
import { pesos } from './filtros-catalogo.js';

const MAX_EJEMPLARES = 5;

export class RenglonesReserva {
  constructor(fieldset, resumen) {
    this.fieldset = fieldset;
    this.resumen = resumen;
    this.maxLibros = Number(fieldset.dataset.maxLibros) || 10;
    // Los ids nuevos siguen después de los que vinieron del servidor, que pueden tener errores
    // enlazados desde el resumen de arriba: no se renumeran.
    this.siguiente = this.renglones().length;

    for (const renglon of this.renglones()) this.prepararRenglon(renglon);

    this.botonAgregar = Object.assign(document.createElement('button'), {
      type: 'button', textContent: '+ Agregar otro libro', className: 'agregar-renglon',
    });
    this.botonAgregar.addEventListener('click', () => this.agregarRenglon());
    const ultimo = this.renglones().at(-1);
    ultimo.after(this.botonAgregar);

    this.aviso = Object.assign(document.createElement('p'), { className: 'solo-lectores' });
    this.aviso.setAttribute('role', 'status');
    this.fieldset.append(this.aviso);

    this.fieldset.addEventListener('change', () => this.actualizar());
    this.fieldset.addEventListener('input', () => this.actualizar());
    this.actualizar();
  }

  renglones() {
    return [...this.fieldset.querySelectorAll('[data-renglon]')];
  }

  prepararRenglon(renglon) {
    const quitar = Object.assign(document.createElement('button'), {
      type: 'button', textContent: 'Quitar', className: 'quitar-renglon',
    });
    quitar.addEventListener('click', () => this.quitarRenglon(renglon));
    renglon.append(quitar);
  }

  agregarRenglon() {
    const modelo = this.renglones().at(-1);
    const nuevo = modelo.cloneNode(true);
    const i = this.siguiente++;

    // Un renglón limpio: sin elección, cantidad 1, sin los errores del que se copió.
    nuevo.querySelector('.quitar-renglon')?.remove();
    for (const error of nuevo.querySelectorAll('p[id^="error-"]')) error.remove();
    const select = nuevo.querySelector('select');
    const cantidad = nuevo.querySelector('input[type="number"]');
    for (const control of [select, cantidad]) {
      control.removeAttribute('aria-invalid');
      control.removeAttribute('aria-describedby');
    }
    select.id = `libro-${i}`;
    select.value = '';
    cantidad.id = `cantidad-${i}`;
    cantidad.value = '1';
    const [etiquetaLibro, etiquetaCantidad] = nuevo.querySelectorAll('label');
    etiquetaLibro.htmlFor = select.id;
    etiquetaCantidad.htmlFor = cantidad.id;

    this.prepararRenglon(nuevo);
    modelo.after(nuevo);
    this.actualizar();
    select.focus();
  }

  quitarRenglon(renglon) {
    const todos = this.renglones();
    if (todos.length <= 1) return;
    const lugar = todos.indexOf(renglon);
    const titulo = renglon.querySelector('select').selectedOptions[0]?.dataset.titulo;
    renglon.remove();
    this.actualizar();
    // El foco va al renglón que quedó en su lugar, para no perderlo.
    const resto = this.renglones();
    resto[Math.min(lugar, resto.length - 1)].querySelector('select').focus();
    this.avisar(titulo ? `Quitaste «${titulo}» de la reserva.` : 'Quitaste un renglón.');
  }

  actualizar() {
    const renglones = this.renglones();
    const elegidos = renglones.map((r) => r.querySelector('select').value).filter((v) => v !== '');

    renglones.forEach((renglon, n) => {
      const select = renglon.querySelector('select');
      const cantidad = renglon.querySelector('input[type="number"]');

      // «Libro 1», «Libro 2»…; con uno solo, el texto de siempre.
      const etiqueta = renglon.querySelector('label');
      etiqueta.firstChild.textContent = renglones.length > 1 ? `Libro ${n + 1} ` : 'Seleccioná el libro a reservar ';

      // Los elegidos en otro renglón, y los sin stock, no se pueden elegir acá.
      for (const opcion of select.options) {
        if (opcion.value === '') continue;
        const sinStock = opcion.dataset.stock === '0';
        opcion.disabled = sinStock || (opcion.value !== select.value && elegidos.includes(opcion.value));
      }

      const opcion = select.selectedOptions[0];
      const stock = Number(opcion?.dataset.stock);
      cantidad.max = String(Number.isInteger(stock) && opcion?.value ? Math.min(MAX_EJEMPLARES, stock) : MAX_EJEMPLARES);

      renglon.querySelector('.quitar-renglon').hidden = renglones.length <= 1;
    });

    this.botonAgregar.hidden = renglones.length >= this.maxLibros;
    this.dibujarResumen(renglones);
  }

  dibujarResumen(renglones) {
    if (!this.resumen) return;
    const items = [];
    let total = 0;
    for (const renglon of renglones) {
      const opcion = renglon.querySelector('select').selectedOptions[0];
      if (!opcion || opcion.value === '') continue;
      const cantidad = Math.max(1, Number.parseInt(renglon.querySelector('input[type="number"]').value, 10) || 1);
      const precio = Number(opcion.dataset.precio);
      total += precio * cantidad;

      const img = Object.assign(document.createElement('img'), {
        src: `/img/${opcion.dataset.imagen}`, alt: '', width: 60, height: 80,
      });
      const datos = document.createElement('div');
      datos.append(
        Object.assign(document.createElement('h3'), { textContent: opcion.dataset.titulo }),
        Object.assign(document.createElement('p'), { textContent: opcion.dataset.autor }),
        Object.assign(document.createElement('p'), { textContent: `${cantidad} × ${pesos(precio)}` }),
      );
      const li = document.createElement('li');
      li.append(img, datos);
      items.push(li);
    }

    this.resumen.hidden = items.length === 0;
    this.resumen.querySelector('ul').replaceChildren(...items);
    const totalData = this.resumen.querySelector(':scope > p data');
    totalData.value = String(total);
    totalData.textContent = pesos(total);
  }

  avisar(texto) {
    this.aviso.textContent = '';
    setTimeout(() => { this.aviso.textContent = texto; }, 50);
  }
}
