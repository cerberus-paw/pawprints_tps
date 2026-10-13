<?php

declare(strict_types=1);

namespace PawPrints\Core;

use RuntimeException;

/**
 * La persistencia del TP3: el enunciado todavía no pide base de datos, así que cada repositorio
 * lee un archivo JSON de data/. Los controladores sólo usan los métodos del repositorio (buscar,
 * contar, uno por id…) y nunca el archivo, así en el TP5 se cambia esta base por una con PDO
 * y no se toca nada más.
 *
 * Un repositorio concreto va en src/Models/, extiende esta clase y declara su archivo:
 * `protected const FILE = 'libros.json';`
 */
abstract class JsonRepository
{
    protected const FILE = '';

    private readonly string $path;

    /** @var list<array<string, mixed>>|null */
    private ?array $rows = null;

    /**
     * @param string|null $path para usar otro archivo, como en una prueba; si no, data/FILE
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? dirname(__DIR__, 2) . '/data/' . static::FILE;
    }

    /**
     * Se lee una vez por pedido, recién cuando alguien lo necesita.
     *
     * @return list<array<string, mixed>>
     */
    protected function rows(): array
    {
        if ($this->rows === null) {
            $json = is_file($this->path) ? file_get_contents($this->path) : false;
            if ($json === false) {
                throw new RuntimeException("No se pudo leer {$this->path}.");
            }

            $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new RuntimeException("{$this->path} tiene que ser una lista de objetos.");
            }
            $this->rows = $rows;
        }

        return $this->rows;
    }
}
