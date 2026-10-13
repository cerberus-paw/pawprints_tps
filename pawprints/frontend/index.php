<?php

declare(strict_types=1);

/*
 * Front controller: la única puerta de entrada al backend. frontend/ es la raíz pública del
 * servidor, así que todo pedido que no sea un archivo de acá (css, img, las maquetas del TP2)
 * termina en este script, y el código, la configuración y los logs quedan fuera del alcance
 * del navegador.
 */

require __DIR__ . '/../backend/bootstrap.php';

PawPrints\App::run(dirname(__DIR__) . '/backend');
