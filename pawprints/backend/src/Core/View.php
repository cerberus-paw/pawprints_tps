<?php

declare(strict_types=1);

namespace PawPrints\Core;

use LogicException;
use Throwable;

/**
 * Genera el HTML a partir de las plantillas PHP de views/ (patrón Template View): cada dato que
 * le pasa el controlador llega a la plantilla como variable. Devuelve un string y no imprime,
 * así el controlador todavía puede elegir el estado de la Response.
 */
final class View
{
    public function __construct(private readonly string $dir)
    {
    }

    /**
     * @param string               $template sin la extensión: «inicio» es views/inicio.view.php
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = "{$this->dir}/{$template}.view.php";
        if (!is_file($file)) {
            throw new LogicException("No existe la vista {$template}.");
        }

        // En una función aparte, para que la plantilla sólo vea sus datos y no las variables de acá.
        $include = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };

        ob_start();
        try {
            $include($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $error) {
            // Lo que alcanzó a imprimir no puede quedar pegado a la página de error.
            ob_end_clean();
            throw $error;
        }
    }
}
