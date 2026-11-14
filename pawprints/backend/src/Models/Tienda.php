<?php

declare(strict_types=1);

namespace PawPrints\Models;

/**
 * Los datos de la tienda física, en un solo lugar (TP5, consigna 8): el pie de todas las páginas,
 * «Sobre nosotros», la confirmación de la reserva, el correo al cliente y la microdata BookStore
 * los toman de acá. Antes estaban escritos en cada vista, y un cambio de horario había que
 * hacerlo en cuatro lugares. Es una constante y no config.ini porque no cambia entre una
 * máquina y el servidor.
 *
 * App se los pasa a todas las vistas como «tienda».
 */
final class Tienda
{
    public const DATOS = [
        'nombre' => 'PAWPrints',
        'direccion' => [
            'calle' => 'Instituto Uzue',
            'localidad' => 'Mercedes',
            'provincia' => 'Buenos Aires',
            'pais' => 'Argentina',
        ],
        'telefono' => '+54 190168',
        // Para el enlace tel:, sin espacios.
        'telefonoEnlace' => '+54190168',
        'correo' => 'contacto@pawprints.com.ar',
        'horario' => 'lunes a sábados de 9 a 20 h.',
        // El mismo horario en el formato de openingHours de schema.org.
        'horarioSchema' => 'Mo-Sa 09:00-20:00',
    ];
}
