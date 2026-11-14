<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Models\LibroRepository;

/**
 * Controlador para la página de inicio.
 * Responsabilidad: Salvador (Punto 2).
 */
class HomeController extends Controller
{
    /**
     * Los libros de «Recomendaciones del Librero». Hasta el TP4 estaban escritos en la vista, con
     * su precio copiado; desde el TP5 salen de la base, así el precio, la tapa y la microdata de
     * la portada son los del catálogo. La elección sigue siendo del librero: es una lista fija.
     */
    private const RECOMENDADOS = [1, 2, 3, 4];

    public function index(Request $request): Response
    {
        $libros = new LibroRepository($this->db);
        // Uno que se haya sacado del catálogo no deja un hueco: se saltea.
        $recomendados = array_values(array_filter(array_map(
            static fn (int $id): ?array => $libros->porId($id),
            self::RECOMENDADOS,
        )));

        return $this->render('inicio', [
            'title' => 'PAWPrints — Librería en Mercedes',
            'description' => 'Librería independiente en Mercedes, Buenos Aires. Catálogo en línea, envíos a toda Argentina y tienda física.',
            'activePage' => 'inicio',
            'pageClass' => 'page-inicio',
            // El pie marca la tienda como BookStore (TP5, consigna 8).
            'marcarTienda' => true,
            'extraCss' => ['inicio.css', 'carrusel.css'],
            'extraJs' => ['paginas/inicio.js'],
            'recomendados' => $recomendados,
        ]);
    }
}
