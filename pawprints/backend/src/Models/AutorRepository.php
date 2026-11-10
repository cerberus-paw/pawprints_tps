<?php

declare(strict_types=1);

namespace PawPrints\Models;

use RuntimeException;

/**
 * Los autores, en data/autores.json: la reseña que muestra la ficha de cada libro. Están aparte
 * del catálogo porque un autor escribe varios libros y su reseña es una sola.
 *
 * Es lo único que queda en JSON: el catálogo pasó a la base en el TP5, pero ninguna consigna pide
 * editar las reseñas desde el sitio, así que siguen siendo un archivo versionado que se lee y no
 * se escribe. Por eso lo lee esta misma clase, sin la base común que tenían los dos repositorios
 * del TP3.
 * Responsabilidad: Agustina (Punto 4).
 *
 * @phpstan-type Autor array{nombre: string, bio: string, imagen: string|null, imagen_alt: string|null}
 */
final class AutorRepository
{
    private readonly string $path;

    /** @var list<array<string, mixed>>|null */
    private ?array $autores = null;

    /**
     * @param string|null $path para usar otro archivo, como en una prueba; si no, data/autores.json
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__, 2) . '/data/autores.json';
    }

    /**
     * @return Autor|null null si el autor de un libro todavía no tiene reseña, y entonces la
     *                    ficha no muestra la sección
     */
    public function porNombre(string $nombre): ?array
    {
        foreach ($this->autores() as $autor) {
            if ($autor['nombre'] === $nombre) {
                /** @var Autor $autor */
                return $autor;
            }
        }

        return null;
    }

    /**
     * Se lee una vez por pedido, recién cuando alguien lo necesita.
     *
     * @return list<array<string, mixed>>
     */
    private function autores(): array
    {
        if ($this->autores === null) {
            $json = is_file($this->path) ? file_get_contents($this->path) : false;
            if ($json === false) {
                throw new RuntimeException("No se pudo leer {$this->path}.");
            }

            $autores = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($autores) || !array_is_list($autores)) {
                throw new RuntimeException("{$this->path} tiene que ser una lista de objetos.");
            }
            $this->autores = $autores;
        }

        return $this->autores;
    }
}
