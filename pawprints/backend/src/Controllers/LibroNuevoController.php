<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\HttpException;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Models\LibroNuevo;
use PawPrints\Models\LibroNuevoInvalido;
use PawPrints\Models\LibroRepository;
use PawPrints\Models\OpenLibrary;

/**
 * La carga de libros nuevos (TP4, consigna 2): el formulario, con validaciones en línea en el
 * navegador (validador.js), y su envío, que el servidor vuelve a validar con las mismas reglas.
 * Desde el TP5 el libro queda guardado en la base y aparece en el catálogo, y la carga es sólo
 * para el personal que ingresó con su usuario.
 */
class LibroNuevoController extends Controller
{
    private const CAMPOS = ['titulo', 'autor', 'genero', 'formato', 'isbn', 'precio', 'stock', 'resumen', 'descripcion'];

    private ?LibroRepository $libros = null;

    /** GET /libros/nuevo */
    public function formulario(Request $request): Response
    {
        $this->exigirPersonal($request);

        return $this->mostrar([]);
    }

    /** POST /libros/nuevo */
    public function cargar(Request $request): Response
    {
        $this->exigirPersonal($request);
        $this->exigirCsrf($request);

        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $campos[$campo] = $request->input($campo, '');
        }

        $tapa = $request->file('tapa');
        $tapaEnviada = is_array($tapa) && ($tapa['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        try {
            $libro = LibroNuevo::desdeFormulario(
                $campos,
                $this->libros(),
                $tapa,
                fn (array $tapaValida, string $mime): string => $this->guardarTapa($tapaValida, $mime)
            );
        } catch (LibroNuevoInvalido $error) {
            return $this->mostrar($campos, $error->errores, 422, $tapaEnviada);
        }

        try {
            $id = $this->libros()->agregar($libro);
        } catch (LibroNuevoInvalido $error) {
            // Otra persona cargó el mismo ISBN entre la validación y el guardado: la tapa que se
            // acaba de guardar no es de ningún libro.
            $this->borrarTapa($libro->imagen);

            return $this->mostrar($campos, $error->errores, 422, $tapaEnviada);
        }

        $this->logger->info('Libro nuevo agregado al catálogo: id {id}, ISBN {isbn}', ['id' => $id, 'isbn' => $libro->isbn]);
        if ($libro->imagen !== null) {
            $this->logger->info('Tapa guardada: {archivo}', ['archivo' => $libro->imagen]);
        }

        // 303 y a otra página, como la reserva: recargar la confirmación no vuelve a mandar el
        // formulario ni carga el libro dos veces.
        return Response::redirect("/libros/nuevo/confirmacion?libro={$id}", 303);
    }

    /**
     * GET /libros/nuevo/confirmacion?libro={id} — lo que quedó guardado, leído de la base, con el
     * enlace a la ficha del libro en el catálogo.
     */
    public function confirmacion(Request $request): Response
    {
        $this->exigirPersonal($request);

        $id = filter_var((string) $request->query('libro', ''), FILTER_VALIDATE_INT);
        $libro = $id === false ? null : $this->libros()->porId($id);
        if ($libro === null) {
            throw HttpException::notFound('No encontramos el libro que buscás.');
        }

        return $this->render('libro-nuevo-confirmacion', [
            'title' => 'Libro cargado — PAWPrints',
            'description' => 'El libro nuevo ya está en el catálogo.',
            'activePage' => 'libro-nuevo',
            'pageClass' => 'page-libro-nuevo-confirmacion',
            'extraCss' => ['libro-nuevo.css'],
            'libro' => $libro,
            'sinTapa' => $libro['imagen'] === LibroRepository::SIN_TAPA,
        ]);
    }

    /**
     * GET /libros/isbn/{isbn} — los datos de Open Library de un ISBN, en JSON, para el
     * autorrelleno de la carga (TP5, consigna 7). El navegador le pide a esta ruta y no a Open
     * Library: así la CSP no abre connect-src a otro dominio.
     *
     * - 200 con titulo, autor, descripcion y tapa (la URL de la vista previa, o null);
     * - 400 si el ISBN no es válido: no se sale a buscar algo que no puede existir;
     * - 404 si Open Library no lo tiene, y 503 si no respondió;
     * - 401 si no ingresó nadie del personal.
     *
     * Todas las respuestas, salvo la 400 y la 401, dicen en «catalogo» si ese ISBN ya está
     * cargado, para avisar antes de cargarlo dos veces.
     */
    public function isbn(Request $request): Response
    {
        // Un fetch no puede seguir la redirección al ingreso como una página: se le contesta 401
        // y el formulario avisa que hay que volver a ingresar.
        if ($this->sesion->personal() === null) {
            return Response::json(['error' => 'Tu sesión venció: volvé a ingresar para buscar los datos del libro.'], 401);
        }
        $this->exigirPersonal($request);

        $isbn = trim((string) $request->param('isbn'));
        $digitos = (string) preg_replace('/\D/', '', $isbn);
        if (preg_match('/^' . LibroNuevo::PATRON_ISBN . '$/', $isbn) !== 1) {
            return Response::json(['error' => LibroNuevo::MENSAJES['isbn']['formato']], 400);
        }
        if (!LibroNuevo::isbnValido($digitos)) {
            return Response::json(['error' => LibroNuevo::MENSAJES['isbn']['isbn']], 400);
        }

        $cargado = $this->libros()->porIsbn($digitos);
        $catalogo = $cargado === null ? null : ['id' => $cargado['id'], 'titulo' => $cargado['titulo']];

        $datos = OpenLibrary::desdeConfig($this->config, $this->logger)->buscarPorIsbn($digitos);
        if ($datos === false) {
            return Response::json([
                'error' => 'Open Library no responde en este momento. Podés completar los datos a mano.',
                'catalogo' => $catalogo,
            ], 503);
        }
        if ($datos === null) {
            return Response::json([
                'error' => 'Open Library no tiene ese ISBN. Completá los datos a mano.',
                'catalogo' => $catalogo,
            ], 404);
        }

        return Response::json($datos + ['catalogo' => $catalogo]);
    }

    /**
     * Guarda la imagen de la tapa en frontend/img/tapas/ con un nombre aleatorio seguro.
     *
     * @param array<string, mixed> $tapa
     */
    private function guardarTapa(array $tapa, string $mime): string
    {
        $extension = LibroNuevo::MIMES_TAPA[$mime] ?? 'jpg';
        $nombreGenerado = bin2hex(random_bytes(8)) . '.' . $extension;
        $directorio = dirname(__DIR__, 3) . '/frontend/img/tapas';

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $destino = $directorio . '/' . $nombreGenerado;
        $tmpName = (string) ($tapa['tmp_name'] ?? '');

        // move_uploaded_file para peticiones HTTP reales; rename/copy como soporte para CLI o pruebas.
        if (!@move_uploaded_file($tmpName, $destino)) {
            if (!@rename($tmpName, $destino)) {
                copy($tmpName, $destino);
            }
        }

        return $nombreGenerado;
    }

    /** Borra una tapa guardada en frontend/img/tapas/, si la hay. */
    private function borrarTapa(?string $archivo): void
    {
        // basename: el nombre lo genera guardarTapa(), pero borrar un archivo pide estar seguro
        // de que no se sale de la carpeta.
        $ruta = dirname(__DIR__, 3) . '/frontend/img/tapas/' . basename((string) $archivo);
        if ($archivo !== null && is_file($ruta)) {
            unlink($ruta);
        }
    }

    /**
     * El formulario, vacío o con lo que ya escribió el usuario y sus errores.
     *
     * @param array<string, string> $valores
     * @param array<string, string> $errores
     */
    private function mostrar(array $valores, array $errores = [], int $estado = 200, bool $tapaEnviada = false): Response
    {
        return $this->render('libro-nuevo', [
            'title' => 'Cargar un libro nuevo — PAWPrints',
            'description' => 'Formulario del personal de PAWPrints para cargar un libro nuevo en el catálogo.',
            'activePage' => 'libro-nuevo',
            'pageClass' => 'page-libro-nuevo',
            'extraCss' => ['libro-nuevo.css'],
            'extraJs' => ['paginas/libro-nuevo.js'],
            'valores' => $valores + array_fill_keys(self::CAMPOS, ''),
            'errores' => $errores,
            'tapaEnviada' => $tapaEnviada,
            'generos' => array_keys($this->libros()->generos()),
            'formatos' => array_keys($this->libros()->formatos()),
            'csrf' => $this->sesion->csrf(),
            'mensajes' => LibroNuevo::MENSAJES,
            'patronIsbn' => LibroNuevo::PATRON_ISBN,
            'limites' => [
                'titulo' => LibroNuevo::MAX_TITULO,
                'autor' => LibroNuevo::MAX_AUTOR,
                'resumen' => LibroNuevo::MAX_RESUMEN,
                'descripcion' => LibroNuevo::MAX_DESCRIPCION,
                'precio' => LibroNuevo::MAX_PRECIO,
                'stock' => LibroNuevo::MAX_STOCK,
            ],
        ], $estado);
    }

    private function libros(): LibroRepository
    {
        return $this->libros ??= new LibroRepository($this->db);
    }
}
