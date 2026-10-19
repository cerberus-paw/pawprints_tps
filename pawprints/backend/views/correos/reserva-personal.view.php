<?php
/**
 * Correo al personal con una reserva nueva. Es texto plano, así que nada se escapa como HTML.
 *
 * Va en un heredoc y no intercalando PHP en el texto porque PHP se come el salto de línea que
 * sigue a cada etiqueta de cierre, y en un texto plano eso pega las líneas.
 *
 * @var PawPrints\Models\Reserva $reserva
 */
$precio = static fn (int $pesos): string => '$' . number_format($pesos, 0, ',', '.');

$libro = $reserva->libro;
$unitario = $precio($libro['precio']);
$total = $precio($reserva->total());
$comentarios = $reserva->comentarios !== '' ? $reserva->comentarios : '(sin comentarios)';

echo <<<TXT
    Llegó una reserva desde el sitio.

    Libro:     {$libro['titulo']} — {$libro['autor']} (id {$libro['id']})
    Cantidad:  {$reserva->cantidad}
    Precio:    {$unitario} c/u, {$total} en total

    Nombre:    {$reserva->nombre}
    Correo:    {$reserva->email}
    Teléfono:  {$reserva->telefono}

    Comentarios:
    {$comentarios}

    Respondiendo este correo le escribís directo a {$reserva->nombre}.

    TXT;
