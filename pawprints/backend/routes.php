<?php

declare(strict_types=1);

use PawPrints\Controllers\HomeController;
use PawPrints\Controllers\MaquetaController;
use PawPrints\Controllers\ReservaController;
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

    // El mismo /reserva muestra el formulario (GET) y lo recibe (POST).
    $router->get('/reserva', [ReservaController::class, 'formulario']);
    $router->post('/reserva', [ReservaController::class, 'enviar']);
    $router->get('/reserva/confirmacion', [ReservaController::class, 'confirmacion']);

    // Provisorias, hasta el punto 4: ver MaquetaController.
    $router->get('/catalogo', [MaquetaController::class, 'catalogo']);
    $router->get('/libros/{id}', [MaquetaController::class, 'libro']);
};
