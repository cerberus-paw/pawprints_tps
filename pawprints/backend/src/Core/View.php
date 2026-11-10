<?php

declare(strict_types=1);

namespace PawPrints\Core;

use LogicException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

/**
 * Genera el HTML a partir de las plantillas de views/ (patrón Template View): cada dato que le
 * pasa el controlador llega a la plantilla como variable. Devuelve un string y no imprime, así el
 * controlador todavía puede elegir el estado de la Response.
 *
 * Desde el TP5 las plantillas son de Twig: .html.twig las páginas y .txt.twig los correos, que
 * son de texto plano. El controlador nombra la plantilla sin la extensión, así que pasar del
 * .view.php del TP3 a Twig no cambió ningún controlador.
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
            // El escape sale de la extensión de la plantilla: en un .html.twig, todo lo que se
            // imprime con {{ }} sale escapado para HTML salvo que se pida lo contrario, que es la
            // defensa principal contra el XSS (TP5, consigna 6). En un .txt.twig (los correos de
            // texto plano) no se escapa: ahí un & tiene que llegar como &, y no como &amp;.
            'autoescape' => 'name',
            'strict_variables' => $debug,
            'cache' => $cache,
            // Con caché, igual revisa si la plantilla cambió: publicar una versión nueva no
            // obliga a acordarse de borrar backend/cache/.
            'auto_reload' => true,
        ]);

        // «$16.500»: el precio como se lee en la Argentina, sin centavos. Es el mismo formato que
        // pesos() en filtros-catalogo.js, así la tarjeta del servidor y la que arma el JS coinciden.
        $this->twig->addFilter(new TwigFilter(
            'pesos',
            static fn (int|string $monto): string => '$' . number_format((int) $monto, 0, ',', '.'),
        ));

        // Un identificador válido para un id del HTML a partir de un texto, como el género de un
        // filtro: «Ciencia Ficción» → «ciencia-ficcion». Twig no trae reemplazos con expresiones
        // regulares, y armarlo en cada controlador lo repetiría.
        $this->twig->addFilter(new TwigFilter(
            'slug',
            static fn (string $texto): string => preg_replace('/[^a-z0-9]+/', '-', strtolower(
                strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '&' => 'y']),
            )) ?? '',
        ));
    }

    /** Un dato para todas las plantillas que se generen de acá en adelante. */
    public function compartir(string $nombre, mixed $valor): void
    {
        $this->compartidos[$nombre] = $valor;
    }

    /**
     * @param string               $template sin la extensión: «inicio» es views/inicio.html.twig,
     *                                       y «correos/reserva-cliente», el .txt.twig
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        foreach (['html', 'txt'] as $tipo) {
            if (is_file("{$this->dir}/{$template}.{$tipo}.twig")) {
                // Lo que pasa el controlador gana sobre lo compartido.
                return $this->twig->render("{$template}.{$tipo}.twig", $data + $this->compartidos);
            }
        }

        throw new LogicException("No existe la vista {$template}.");
    }
}
