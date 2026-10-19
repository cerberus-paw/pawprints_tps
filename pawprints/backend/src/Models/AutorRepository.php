<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\JsonRepository;

/**
 * Los autores, en data/autores.json: la reseña que muestra la ficha de cada libro. Están aparte
 * del catálogo porque un autor escribe varios libros y su reseña es una sola.
 * Responsabilidad: Agustina (Punto 4).
 *
 * @phpstan-type Autor array{nombre: string, bio: string, imagen: string|null, imagen_alt: string|null}
 */
final class AutorRepository extends JsonRepository
{
    protected const FILE = 'autores.json';

    /**
     * @return Autor|null null si el autor de un libro todavía no tiene reseña, y entonces la
     *                    ficha no muestra la sección
     */
    public function porNombre(string $nombre): ?array
    {
        foreach ($this->rows() as $autor) {
            if ($autor['nombre'] === $nombre) {
                /** @var Autor $autor */
                return $autor;
            }
        }

        return null;
    }
}
