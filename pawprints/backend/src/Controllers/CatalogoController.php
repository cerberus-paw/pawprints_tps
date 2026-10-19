<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Csv;
use PawPrints\Core\HttpException;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Models\AutorRepository;
use PawPrints\Models\LibroRepository;

/**
 * El catálogo: el listado con búsqueda, filtros, orden y paginado; la ficha de cada libro, con el
 * botón que lleva a la reserva; y el mismo listado en CSV.
 * Responsabilidad: Agustina (Punto 4).
 */
class CatalogoController extends Controller
{
    /**
     * Lo que forma «la vista actual» del catálogo y por eso viaja en los enlaces del paginado y
     * del CSV. El número de página no está acá: se agrega aparte.
     */
    private const FILTROS = ['q', 'genero', 'formato', 'precio', 'orden'];

    /**
     * Los órdenes que ofrece el desplegable, en el orden en que se muestran. La clave es el valor
     * que entiende LibroRepository::buscar(); el primero es el de por defecto.
     */
    private const ORDENES = [
        'recomendados' => 'Recomendados',
        'precio-asc' => 'Precio: Menor a Mayor',
        'precio-desc' => 'Precio: Mayor a Menor',
        'titulo-az' => 'Título: A - Z',
    ];

    private ?LibroRepository $libros = null;

    private ?AutorRepository $autores = null;

    /** GET /catalogo, con la búsqueda, los filtros, el orden y la página en la query. */
    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $resultados = $this->libros()->buscar($filtros);
        $total = count($resultados);

        // Sin resultados sigue habiendo una página 1, que es la que muestra «no encontramos nada».
        $paginas = max(1, (int) ceil($total / LibroRepository::POR_PAGINA));
        $pagina = filter_var(
            (string) $request->query('pagina', '1'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        // Una página que no existe es un 404 y no una página vacía: un enlace viejo o un número
        // escrito a mano no tiene que parecer un catálogo que se quedó sin libros.
        if ($pagina === false || $pagina > $paginas) {
            throw HttpException::notFound('No encontramos esa página del catálogo.');
        }

        return $this->render('catalogo', [
            'title' => 'Catálogo — PAWPrints',
            'description' => 'Catálogo completo de la librería PAWPrints: buscá por título, autor, género o ISBN.',
            'activePage' => 'catalogo',
            'pageClass' => 'page-catalogo',
            'extraCss' => ['catalogo.css'],
            'libros' => array_slice(
                $resultados,
                ($pagina - 1) * LibroRepository::POR_PAGINA,
                LibroRepository::POR_PAGINA,
            ),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'filtros' => $filtros,
            'generos' => $this->libros()->generos(),
            'formatos' => $this->libros()->formatos(),
            'rangos' => self::rangos(),
            'ordenes' => self::ORDENES,
        ]);
    }

    /** GET /libros/{id} */
    public function libro(Request $request): Response
    {
        $libro = $this->buscado($request);
        $autor = $this->autores()->porNombre($libro['autor']);

        return $this->render('libro', [
            'title' => "{$libro['titulo']} — PAWPrints",
            'description' => $libro['resumen'],
            'activePage' => 'catalogo',
            'pageClass' => 'page-detalle-libro',
            'extraCss' => ['detalle-libro.css'],
            'libro' => $libro,
            'autor' => $autor,
            'recomendados' => $this->libros()->recomendados($libro),
        ]);
    }

    /**
     * GET /catalogo/csv — el listado que se está viendo, entero y sin paginar: el CSV se abre en
     * una planilla, donde las páginas no significan nada.
     */
    public function csv(Request $request): Response
    {
        $libros = $this->libros()->buscar($this->filtros($request));

        $filas = array_map(
            static fn (array $libro): array => [
                $libro['id'],
                $libro['titulo'],
                $libro['autor'],
                $libro['genero'],
                $libro['isbn'],
                $libro['formato'],
                $libro['precio'],
                $libro['stock'],
            ],
            $libros,
        );

        $csv = Csv::generate(
            ['id', 'titulo', 'autor', 'genero', 'isbn', 'formato', 'precio', 'stock'],
            $filas,
        );

        $this->logger->info('Catálogo exportado a CSV: {libros} libro(s)', ['libros' => count($libros)]);

        return Response::download($csv, 'text/csv; charset=utf-8', 'catalogo-pawprints.csv');
    }

    /**
     * El libro de /libros/{id}, o un 404 si el id no es un número o no está en el catálogo. El
     * mensaje es el mismo en los dos casos: al visitante le da igual por qué, y así la respuesta
     * no cuenta qué ids existen.
     *
     * @return array<string, mixed>
     */
    private function buscado(Request $request): array
    {
        $id = filter_var((string) $request->param('id'), FILTER_VALIDATE_INT);
        $libro = $id === false ? null : $this->libros()->porId($id);

        if ($libro === null) {
            throw HttpException::notFound('No encontramos el libro que buscás.');
        }

        return $libro;
    }

    /**
     * Los filtros de la query, sin los vacíos: así los enlaces que se armen con ellos no arrastran
     * un «?q=&genero=» que no filtra nada.
     *
     * @return array<string, string>
     */
    private function filtros(Request $request): array
    {
        $filtros = [];
        foreach (self::FILTROS as $nombre) {
            $valor = trim((string) $request->query($nombre, ''));
            if ($valor !== '') {
                $filtros[$nombre] = $valor;
            }
        }

        return $filtros;
    }

    /**
     * Las etiquetas de los rangos de precio, armadas con los mismos números que usa el filtro,
     * para que no se puedan desfasar de LibroRepository::RANGOS.
     *
     * @return array<string, string> valor de la query => etiqueta
     */
    private static function rangos(): array
    {
        $pesos = static fn (int $monto): string => '$' . number_format($monto, 0, ',', '.');

        $etiquetas = [];
        foreach (LibroRepository::RANGOS as $valor => [$desde, $hasta]) {
            $etiquetas[$valor] = match (true) {
                $desde === 0 && $hasta !== null => 'Menos de ' . $pesos($hasta + 1),
                $hasta === null => 'Más de ' . $pesos($desde - 1),
                default => $pesos($desde) . ' - ' . $pesos($hasta),
            };
        }

        return $etiquetas;
    }

    /** Protected, para que una prueba pueda darle un catálogo de mentira. */
    protected function libros(): LibroRepository
    {
        return $this->libros ??= new LibroRepository();
    }

    protected function autores(): AutorRepository
    {
        return $this->autores ??= new AutorRepository();
    }
}
