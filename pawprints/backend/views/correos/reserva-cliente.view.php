<?php
/**
 * Confirmación al cliente de que la reserva llegó. Es texto plano, como el aviso al personal.
 *
 * No repite los comentarios: cualquiera puede escribir la dirección de otro en el formulario, y
 * así el correo no sirve para mandarle un texto propio a un tercero.
 *
 * @var PawPrints\Models\Reserva $reserva
 */
$libro = $reserva->libro;
$ejemplares = $reserva->cantidad === 1 ? 'un ejemplar' : "{$reserva->cantidad} ejemplares";

echo <<<TXT
    Hola, {$reserva->nombre}:

    Recibimos tu reserva de {$ejemplares} de «{$libro['titulo']}», de {$libro['autor']}.

    La reserva queda guardada por 10 días hábiles, y se paga al retirarla en la tienda:
    Instituto Uzue, Mercedes, Buenos Aires. Lunes a sábados de 9 a 20 h.

    Si tenés alguna pregunta o querés cancelarla, respondé este correo.

    PAWPrints — Librería

    TXT;
