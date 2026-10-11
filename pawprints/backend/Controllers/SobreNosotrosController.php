<?php

namespace PawPrints\Controllers;

/**
 * Controlador para la sección "Acerca de nosotros".
 * Responsabilidad: Salvador (Punto 3).
 */
class SobreNosotrosController
{
    public function index(): void
    {
        $title = 'Sobre Nosotros — PAWPrints';
        $description = 'La historia de PAWPrints, su misión y los servicios que ofrece a la comunidad de Mercedes.';
        $activePage = 'sobre-nosotros';
        $pageClass = 'page-sobre-nosotros';
        $extraCss = ['sobre-nosotros.css'];

        require __DIR__ . '/../views/sobre-nosotros.view.php';
    }
}
