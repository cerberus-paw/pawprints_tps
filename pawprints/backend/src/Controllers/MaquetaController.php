<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Request;
use PawPrints\Core\Response;

/**
 * Provisorio: lleva a las maquetas estáticas del TP2 las páginas que todavía no tienen su
 * controlador, para que el menú no quede roto mientras tanto. Cada método se borra, junto con su
 * ruta, cuando llega el controlador de verdad (catálogo y libro en el punto 4).
 */
class MaquetaController extends Controller
{
    public function catalogo(Request $request): Response
    {
        return Response::redirect('/catalogo.html');
    }

    public function libro(Request $request): Response
    {
        return Response::redirect('/detalle-libro.html');
    }
}
