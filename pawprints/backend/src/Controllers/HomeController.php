<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Request;
use PawPrints\Core\Response;

/**
 * Controlador para la página de inicio.
 * Responsabilidad: Salvador (Punto 2).
 */
class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('inicio', [
            'title' => 'PAWPrints — Librería en Mercedes',
            'description' => 'Librería independiente en Mercedes, Buenos Aires. Catálogo en línea, envíos a toda Argentina y tienda física.',
            'activePage' => 'inicio',
            'pageClass' => 'page-inicio',
            'extraCss' => ['inicio.css'],
        ]);
    }
}
