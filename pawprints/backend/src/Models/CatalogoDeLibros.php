<?php

declare(strict_types=1);

namespace PawPrints\Models;

/**
 * Lo que la reserva necesita del catálogo: la lista de libros para el desplegable y un libro por
 * su id para validar el elegido. Lo implementa el repositorio de libros del punto 4; la reserva
 * depende sólo de esto, y no de cómo ni dónde se guardan los libros.
 */
interface CatalogoDeLibros
{
    /**
     * @return list<array{id: int, titulo: string, autor: string, precio: int}> el precio, en pesos
     */
    public function todos(): array;

    /**
     * @return array{id: int, titulo: string, autor: string, precio: int}|null null si no existe
     */
    public function porId(int $id): ?array;
}
