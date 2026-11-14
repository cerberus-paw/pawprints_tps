<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Config;
use PawPrints\Core\Logger;

/**
 * El cliente de la API de Open Library (TP5, consigna 7): los datos de un libro por su ISBN, para
 * autocompletar la carga, y la tapa, para un libro que se guarda sin una.
 *
 * El navegador nunca le habla a Open Library: pasa por la ruta /libros/isbn/{isbn}. Así la CSP no
 * abre connect-src a otro dominio y el mismo cliente sirve para buscar la tapa al guardar.
 *
 * Nada de acá lanza una excepción: si Open Library no responde o responde algo que no se
 * entiende, queda en el log y el formulario se completa a mano, como en el TP4.
 */
final class OpenLibrary
{
    /** Los segundos que se espera cada respuesta: el personal está mirando el formulario. */
    private const ESPERA = 5;

    /** Sin un User-Agent propio Open Library limita los pedidos; con él sabe de dónde vienen. */
    private const AGENTE = 'PAWPrints/5 (TP de Programación en Ambiente Web, UNLu)';

    public function __construct(
        private readonly Logger $logger,
        private readonly string $api = 'https://openlibrary.org',
        private readonly string $tapas = 'https://covers.openlibrary.org',
    ) {
    }

    /** Las direcciones salen de config.ini, para poder apuntarlas a otro servidor en una prueba. */
    public static function desdeConfig(Config $config, Logger $logger): self
    {
        return new self(
            $logger,
            rtrim((string) $config->get('open_library.api', 'https://openlibrary.org'), '/'),
            rtrim((string) $config->get('open_library.tapas', 'https://covers.openlibrary.org'), '/'),
        );
    }

    /**
     * Los datos de un libro con nuestras claves: titulo, autor, descripcion (vacía si no tiene) y
     * tapa (la URL de la tapa mediana, para la vista previa, o null).
     *
     * @return array{titulo: string, autor: string, descripcion: string, tapa: ?string}|null|false
     *         null si Open Library no tiene ese ISBN; false si no respondió o no se le entendió
     */
    public function buscarPorIsbn(string $isbn): array|null|false
    {
        $digitos = (string) preg_replace('/\D/', '', $isbn);
        $clave = "ISBN:{$digitos}";

        $respuesta = $this->json("{$this->api}/api/books?" . http_build_query([
            'bibkeys' => $clave,
            'format' => 'json',
            'jscmd' => 'data',
        ]));
        if ($respuesta === null) {
            return false;
        }

        // Un ISBN que no tiene da un objeto vacío, no un 404.
        $libro = $respuesta[$clave] ?? null;
        if (!is_array($libro) || !is_string($libro['title'] ?? null)) {
            return null;
        }

        $autores = array_filter(array_map(
            static fn (mixed $autor): string => is_array($autor) ? trim((string) ($autor['name'] ?? '')) : '',
            is_array($libro['authors'] ?? null) ? $libro['authors'] : [],
        ));

        $tapa = $libro['cover']['medium'] ?? null;

        return [
            'titulo' => self::titulo(trim($libro['title']), trim((string) ($libro['subtitle'] ?? ''))),
            'autor' => self::recortar(implode(', ', $autores), LibroNuevo::MAX_AUTOR),
            'descripcion' => $this->descripcion((string) ($libro['key'] ?? '')),
            // Sólo una URL de sus tapas: la vista previa la carga directo, y la CSP sólo deja
            // cargar imágenes de ese dominio.
            'tapa' => is_string($tapa) && str_starts_with($tapa, 'https://covers.openlibrary.org/') ? $tapa : null,
        ];
    }

    /**
     * La tapa grande de un libro, ya validada como las que se suben a mano: un JPG, PNG o WebP de
     * hasta 2 MB.
     *
     * @return array{contenido: string, mime: string}|null null si no tiene, no respondió o lo que
     *         vino no es una imagen que se pueda guardar
     */
    public function tapa(string $isbn): ?array
    {
        $digitos = (string) preg_replace('/\D/', '', $isbn);
        // default=false: sin tapa responde 404 en lugar de una imagen en blanco.
        $url = "{$this->tapas}/b/isbn/{$digitos}-L.jpg?default=false";

        // Un byte más que el máximo, para saber si se pasa sin bajar una imagen entera de más.
        [$estado, $contenido] = $this->pedir($url, LibroNuevo::MAX_TAMANO_TAPA + 1);
        if ($estado !== 200 || $contenido === '') {
            if ($estado !== 404) {
                $this->logger->warning('Open Library no dio la tapa de {isbn}: estado {estado}', ['isbn' => $digitos, 'estado' => $estado ?? 'sin respuesta']);
            }

            return null;
        }

        if (strlen($contenido) > LibroNuevo::MAX_TAMANO_TAPA) {
            $this->logger->notice('La tapa de Open Library de {isbn} pesa más de 2 MB: no se guarda', ['isbn' => $digitos]);

            return null;
        }

        // El tipo real, como en las subidas: no se confía en la extensión ni en el Content-Type.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if (!is_string($mime) || !array_key_exists($mime, LibroNuevo::MIMES_TAPA)) {
            $this->logger->notice('La tapa de Open Library de {isbn} no es una imagen que se pueda guardar ({mime})', ['isbn' => $digitos, 'mime' => $mime]);

            return null;
        }

        return ['contenido' => $contenido, 'mime' => $mime];
    }

    /**
     * La descripción no viene en /api/books: está en la edición o, si la edición no tiene, en la
     * obra. Es lo menos importante de lo que se completa, así que si falla queda vacía.
     */
    private function descripcion(string $edicion): string
    {
        if (preg_match('#^/books/OL\d+M$#', $edicion) !== 1) {
            return '';
        }

        $datos = $this->json("{$this->api}{$edicion}.json");
        $texto = self::texto($datos['description'] ?? null);

        $obra = $datos['works'][0]['key'] ?? null;
        if ($texto === '' && is_string($obra) && preg_match('#^/works/OL\d+W$#', $obra) === 1) {
            $texto = self::texto($this->json("{$this->api}{$obra}.json")['description'] ?? null);
        }

        return self::recortar($texto, LibroNuevo::MAX_DESCRIPCION);
    }

    /**
     * Un JSON de Open Library ya decodificado, o null si no respondió, respondió con error o no es
     * un objeto.
     *
     * @return array<mixed>|null
     */
    private function json(string $url): ?array
    {
        [$estado, $cuerpo] = $this->pedir($url);
        if ($estado !== 200) {
            $this->logger->warning('Open Library no respondió {url}: estado {estado}', ['url' => $url, 'estado' => $estado ?? 'sin respuesta']);

            return null;
        }

        $datos = json_decode($cuerpo, true);
        if (!is_array($datos)) {
            $this->logger->warning('Open Library respondió algo que no es JSON en {url}', ['url' => $url]);

            return null;
        }

        return $datos;
    }

    /**
     * Un GET con tiempo máximo. ignore_errors deja leer el estado de un 404 en lugar de un aviso
     * de PHP, que el ErrorHandler convertiría en excepción.
     *
     * @return array{0: ?int, 1: string} el estado de la última respuesta (después de las
     *         redirecciones), o null si no hubo, y el cuerpo
     */
    private function pedir(string $url, ?int $maximo = null): array
    {
        $contexto = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => self::ESPERA,
            'user_agent' => self::AGENTE,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 3,
        ]]);

        // Con @: sin conexión, PHP avisa y devuelve false; eso ya es la respuesta que se maneja.
        $cuerpo = $maximo === null
            ? @file_get_contents($url, false, $contexto)
            : @file_get_contents($url, false, $contexto, 0, $maximo);
        if ($cuerpo === false) {
            return [null, ''];
        }

        $estado = null;
        foreach (http_get_last_response_headers() ?? [] as $cabecera) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $cabecera, $m) === 1) {
                $estado = (int) $m[1];
            }
        }

        return [$estado, $cuerpo];
    }

    /** El título con el subtítulo, si entra; si no, sólo el título. */
    private static function titulo(string $titulo, string $subtitulo): string
    {
        $completo = $subtitulo === '' ? $titulo : "{$titulo}: {$subtitulo}";

        return mb_strlen($completo) <= LibroNuevo::MAX_TITULO ? $completo : self::recortar($titulo, LibroNuevo::MAX_TITULO);
    }

    /** La descripción viene como texto o como {type: "/type/text", value: "…"}. */
    private static function texto(mixed $descripcion): string
    {
        if (is_array($descripcion)) {
            $descripcion = $descripcion['value'] ?? '';
        }
        if (!is_string($descripcion)) {
            return '';
        }

        // Los mismos saltos de línea que el textarea, y sin los caracteres de control que la
        // validación de LibroNuevo rechazaría.
        $texto = str_replace("\r\n", "\n", $descripcion);

        return trim((string) preg_replace('/[^\P{Cc}\n\t]/u', '', $texto));
    }

    /** Para que lo que se completa entre en el campo: un texto más largo no pasaría la validación. */
    private static function recortar(string $texto, int $maximo): string
    {
        return mb_strlen($texto) <= $maximo ? $texto : rtrim(mb_substr($texto, 0, $maximo - 1)) . '…';
    }
}
