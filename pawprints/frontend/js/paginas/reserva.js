/**
 * Reserva: renglones que se agregan y se quitan, y la tarjeta de resumen al día (TP5-11).
 */
import { RenglonesReserva } from '../componentes/renglones-reserva.js';

const renglones = document.querySelector('.page-reserva fieldset[data-renglones]');
if (renglones) {
  new RenglonesReserva(renglones, document.querySelector('.page-reserva header[data-resumen]'));
}
