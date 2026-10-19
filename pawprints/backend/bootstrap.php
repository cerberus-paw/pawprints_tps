<?php

declare(strict_types=1);

/*
 * Autoload PSR-4: PawPrints\Core\Router está en src/Core/Router.php. Es a mano porque el TP3 no
 * tiene dependencias de terceros; cuando el TP5 sume Twig con Composer, su autoload declara la
 * misma correspondencia y ninguna clase cambia.
 */
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'PawPrints\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }

    $archivo = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
