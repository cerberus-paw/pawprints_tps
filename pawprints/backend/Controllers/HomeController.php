<?php

namespace PawPrints\Controllers;

/**
 * Controlador para la página de inicio.
 * Responsabilidad: Salvador (Punto 2).
 */
class HomeController
{
    public function index(): void
    {
        $title = 'PAWPrints — Librería en Mercedes';
        $description = 'Librería independiente en Mercedes, Buenos Aires. Catálogo en línea, envíos a toda Argentina y tienda física.';
        $activePage = 'inicio';
        $pageClass = 'page-inicio';
        $extraCss = ['inicio.css'];

        require __DIR__ . '/../views/inicio.view.php';
    }
}
