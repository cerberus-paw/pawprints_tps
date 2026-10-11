<?php
/**
 * PAWPrints — Front Controller
 * Punto de entrada único de la aplicación (Patrón MVC).
 */

// Autoload compatible con PSR-4
spl_autoload_register(function ($class) {
    $prefix = 'PawPrints\\';
    $baseDir = __DIR__ . '/backend/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use PawPrints\App;

// Ejecutar la aplicación
App::run();
