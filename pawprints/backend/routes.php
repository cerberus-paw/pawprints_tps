<?php

declare(strict_types=1);

use PawPrints\Controllers\HomeController;
use PawPrints\Controllers\MaquetaController;
use PawPrints\Controllers\SobreNosotrosController;
use PawPrints\Core\Router;

/*
 * Mapeo de URLs: qué controlador atiende cada método + ruta. Lo que identifica un recurso va en
 * la ruta (/libros/{id}); la búsqueda, los filtros y el paginado van en la query
 * (/catalogo?q=…&pagina=2), porque son la misma página vista de otra forma.
 */
return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/sobre-nosotros', [SobreNosotrosController::class, 'index']);

    // Provisorias, hasta los puntos 4 y 5: ver MaquetaController.
    $router->get('/catalogo', [MaquetaController::class, 'catalogo']);
    $router->get('/libros/{id}', [MaquetaController::class, 'libro']);
    $router->get('/reserva', [MaquetaController::class, 'reserva']);
};
