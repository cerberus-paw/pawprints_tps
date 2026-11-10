<?php

declare(strict_types=1);

namespace PawPrints\Core;

use LogicException;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Genera el HTML a partir de las plantillas de views/ (patrón Template View): cada dato que le
 * pasa el controlador llega a la plantilla como variable. Devuelve un string y no imprime, así el
 * controlador todavía puede elegir el estado de la Response.
 *
 * Desde el TP5 las plantillas son de Twig (.html.twig). Mientras dura la migración conviven con
 * las .view.php del TP3: se busca primero la de Twig y, si no está, la de PHP. Así cada página se
 * pasa por separado y ningún controlador cambia.
 */
final class View
{
    private readonly Environment $twig;

    /**
     * Datos que ven todas las plantillas además de los suyos, como quién del personal ingresó,
     * que cambia el menú. Arranca con las claves que esperan las plantillas, así existen aunque
     * nadie las haya compartido (strict_variables).
     *
     * @var array<string, mixed>
     */
    private array $compartidos = ['personal' => null];

    /**
     * @param string       $dir   la carpeta views/
     * @param string|false $cache dónde guarda Twig las plantillas compiladas, o false para no
     *                            guardarlas (en desarrollo, así un cambio se ve al recargar)
     * @param bool         $debug en desarrollo: una variable que no existe es un error y no un
     *                            texto vacío, para que una errata en la plantilla se note
     */
    public function __construct(
        private readonly string $dir,
        string|false $cache = false,
        bool $debug = false,
    ) {
        $this->twig = new Environment(new FilesystemLoader($dir), [
            // Todo lo que se imprime con {{ }} sale escapado para HTML salvo que se pida lo
            // contrario: es la defensa principal contra el XSS (TP5, consigna 5).
            'autoescape' => 'html',
            'strict_variables' => $debug,
            'cache' => $cache,
            // Con caché, igual revisa si la plantilla cambió: publicar una versión nueva no
            // obliga a acordarse de borrar backend/cache/.
            'auto_reload' => true,
        ]);
    }

    /** Un dato para todas las plantillas que se generen de acá en adelante. */
    public function compartir(string $nombre, mixed $valor): void
    {
        $this->compartidos[$nombre] = $valor;
    }

    /**
     * @param string               $template sin la extensión: «inicio» es views/inicio.html.twig
     *                                       o, si todavía no se migró, views/inicio.view.php
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        // Lo que pasa el controlador gana sobre lo compartido.
        $data += $this->compartidos;

        if (is_file("{$this->dir}/{$template}.html.twig")) {
            return $this->twig->render("{$template}.html.twig", $data);
        }

        $file = "{$this->dir}/{$template}.view.php";
        if (!is_file($file)) {
            throw new LogicException("No existe la vista {$template}.");
        }

        return self::renderPhp($file, $data);
    }

    /**
     * Las plantillas del TP3, hasta que se pasen todas a Twig.
     *
     * @param array<string, mixed> $data
     */
    private static function renderPhp(string $file, array $data): string
    {
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
