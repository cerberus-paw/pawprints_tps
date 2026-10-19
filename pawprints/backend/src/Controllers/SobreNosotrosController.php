<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Request;
use PawPrints\Core\Response;

/**
 * Controlador para la sección "Acerca de nosotros".
 * Responsabilidad: Salvador (Punto 3).
 */
class SobreNosotrosController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('sobre-nosotros', [
            'title' => 'Sobre Nosotros — PAWPrints',
            'description' => 'La historia de PAWPrints, su misión y los servicios que ofrece a la comunidad de Mercedes.',
            'activePage' => 'sobre-nosotros',
            'pageClass' => 'page-sobre-nosotros',
            'extraCss' => ['sobre-nosotros.css'],
        ]);
    }
}
