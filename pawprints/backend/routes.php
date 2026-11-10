<?php

declare(strict_types=1);

use PawPrints\Controllers\CatalogoController;
use PawPrints\Controllers\HomeController;
use PawPrints\Controllers\LibroNuevoController;
use PawPrints\Controllers\PersonalController;
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

    // El catálogo y la ficha de cada libro. El CSV y el JSON son otras representaciones del mismo
    // listado, y por eso son otras rutas y no un parámetro: identifican al recurso, no a la vista.
    $router->get('/catalogo', [CatalogoController::class, 'index']);
    $router->get('/catalogo/csv', [CatalogoController::class, 'csv']);
    $router->get('/catalogo/json', [CatalogoController::class, 'json']);

    // La carga de libros nuevos del TP4. Va antes que /libros/{id}: el router se queda con la
    // primera ruta que coincide, y {id} también atraparía «nuevo».
    $router->get('/libros/nuevo', [LibroNuevoController::class, 'formulario']);
    $router->post('/libros/nuevo', [LibroNuevoController::class, 'cargar']);
    $router->get('/libros/nuevo/confirmacion', [LibroNuevoController::class, 'confirmacion']);
    $router->get('/libros/{id}', [CatalogoController::class, 'libro']);

    // El mismo /reserva muestra el formulario (GET) y lo recibe (POST).
    $router->get('/reserva', [ReservaController::class, 'formulario']);
    $router->post('/reserva', [ReservaController::class, 'enviar']);
    $router->get('/reserva/confirmacion', [ReservaController::class, 'confirmacion']);

    // El personal (TP5): ingresa con su usuario para ver los pedidos y cargar libros. Salir es un
    // POST, como cualquier acción que cambia algo.
    $router->get('/personal/ingresar', [PersonalController::class, 'formulario']);
    $router->post('/personal/ingresar', [PersonalController::class, 'ingresar']);
    $router->post('/personal/salir', [PersonalController::class, 'salir']);
};
