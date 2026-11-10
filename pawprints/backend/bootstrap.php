<?php

declare(strict_types=1);

/*
 * El autoload de Composer, que además de Twig carga las clases propias con la misma
 * correspondencia PSR-4 que tenía el autoload a mano del TP3: PawPrints\Core\Router está en
 * src/Core/Router.php. Sin vendor/ el sitio no puede arrancar, y el mensaje dice qué falta.
 */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Falta vendor/: correr «composer install» en pawprints/backend.');
}

require $autoload;
