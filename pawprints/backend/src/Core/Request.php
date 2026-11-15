<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * La petición HTTP ya analizada. Los controladores la leen desde acá y nunca desde $_GET, $_POST
 * o $_SERVER, así reciben siempre strings y se pueden probar sin un servidor.
 */
final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::normalize(rawurldecode(is_string($path) ? $path : '/')),
            $_GET,
            $_POST,
            $_FILES,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /** La ruta sin la query: «/catalogo», nunca «/catalogo/» ni «/catalogo?q=…». */
    public function path(): string
    {
        return $this->path;
    }

    /** Un parámetro de la query (?q=…): búsqueda, filtros y paginado, que llegan por GET. */
    public function query(string $key, ?string $default = null): ?string
    {
        return self::string($this->query, $key) ?? $default;
    }

    /**
     * Toda la query, para armar links que conserven la vista actual (la página siguiente, o el
     * CSV del catálogo con la misma búsqueda).
     *
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    /** Un campo del cuerpo de un formulario enviado por POST (application/x-www-form-urlencoded). */
    public function input(string $key, ?string $default = null): ?string
    {
        return self::string($this->body, $key) ?? $default;
    }

    /**
     * Un campo del cuerpo que llega como arreglo («libro_id[]=3&libro_id[]=7»): los renglones de
     * la reserva del carrito. Sólo los valores de texto de un nivel; si llegó un texto suelto o
     * algo anidado, se descarta igual que input() descarta un arreglo.
     *
     * @return array<int|string, string>
     */
    public function inputArray(string $key): array
    {
        return self::strings($this->body, $key);
    }

    /**
     * Lo mismo para la query: «/reserva?libros[3]=2&libros[7]=1», el carrito que llega a la reserva.
     *
     * @return array<int|string, string>
     */
    public function queryArray(string $key): array
    {
        return self::strings($this->query, $key);
    }

    /**
     * Un archivo subido ($_FILES): su array con 'name', 'type', 'tmp_name', 'error', 'size',
     * o null si no se envió nada en ese campo.
     *
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * Todos los archivos subidos en la petición ($_FILES).
     *
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /** Un parámetro de la ruta: el {id} de «/libros/{id}». */
    public function param(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    /**
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): self
    {
        $copy = clone $this;
        $copy->routeParams = $params;

        return $copy;
    }

    /**
     * Un «?q[]=a» llega como array: se descarta en lugar de dejar que rompa al primer uso como
     * texto.
     *
     * @param array<string, mixed> $values
     */
    private static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<int|string, string>
     */
    private static function strings(array $values, string $key): array
    {
        $value = $values[$key] ?? null;

        return is_array($value) ? array_filter($value, 'is_string') : [];
    }

    private static function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
