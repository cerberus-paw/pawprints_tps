<?php

namespace PawPrints;

use PawPrints\Router\Router;

/**
 * Clase principal de la aplicación (Bootstrap).
 * Inicializa y delega el flujo de ejecución al enrutador.
 */
class App
{
    public static function run(): void
    {
        $router = new Router();
        $router->dispatch();
    }
}
