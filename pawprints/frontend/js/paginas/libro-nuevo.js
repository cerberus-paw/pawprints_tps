/**
 * Carga de libros nuevos: validación en línea del formulario, con la regla del ISBN que HTML no
 * sabe controlar.
 */
import { AutorrellenoIsbn } from '../componentes/autorrelleno-isbn.js';
import { Validador } from '../componentes/validador.js';
import { ZonaArrastre } from '../componentes/zona-arrastre.js';

/**
 * El dígito verificador del ISBN-13, la misma cuenta que LibroNuevo::isbnValido() en el servidor:
 * los doce primeros números se multiplican, alternando, por 1 y por 3, y el decimotercero es lo
 * que le falta a esa suma para llegar a un múltiplo de 10. El formato (13 números, 978 o 979) ya
 * lo controla el pattern del campo.
 */
function isbnValido(valor) {
  const digitos = valor.replace(/\D/g, '');
  if (digitos.length !== 13) return true; // de eso avisa el pattern, con su propio mensaje
  const suma = [...digitos.slice(0, 12)].reduce((total, d, i) => total + Number(d) * (i % 2 === 0 ? 1 : 3), 0);
  return (10 - (suma % 10)) % 10 === Number(digitos[12]);
}

new Validador(document.querySelector('form[data-validar]')).regla('isbn', isbnValido);

const contenedorTapa = document.querySelector('[data-zona-arrastre]');
const zonaTapa = contenedorTapa ? new ZonaArrastre(contenedorTapa) : null;

// Con el ISBN, el resto de los datos y la tapa salen de Open Library (TP5, consigna 7). Se busca
// sólo un ISBN que pasaría la validación: el mismo pattern del campo y el dígito verificador.
const campoIsbn = document.querySelector('[data-autorrelleno]');
if (campoIsbn) {
  const patron = new RegExp(`^(?:${campoIsbn.pattern})$`);
  new AutorrellenoIsbn(campoIsbn, {
    esValido: (valor) => patron.test(valor) && isbnValido(valor),
    zonaTapa,
  });
}

