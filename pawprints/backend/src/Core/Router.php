<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * Mapea método + ruta al controlador que la atiende. Las rutas se declaran todas juntas en
 * routes.php, así la tabla completa del sitio se lee en un solo lugar.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, action: array{0: class-string, 1: string}}> */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string} $action
     */
    public function get(string $pattern, array $action): void
    {
        $this->add('GET', $pattern, $action);
    }

    /**
     * @param array{0: class-string, 1: string} $action
     */
    public function post(string $pattern, array $action): void
    {
        $this->add('POST', $pattern, $action);
    }

    /**
     * @return array{0: array{0: class-string, 1: string}, 1: array<string, string>} la acción y
     *         los parámetros de la ruta
     *
     * @throws HttpException 404 si la ruta no existe; 405 si existe, pero con otro método
     */
    public function resolve(string $method, string $path): array
    {
        // HEAD es un GET sin cuerpo: lo atiende la misma ruta.
        $wanted = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] === $wanted) {
                return [$route['action'], array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
            $allowed[] = $route['method'];
        }

        if ($allowed !== []) {
            throw HttpException::methodNotAllowed(array_values(array_unique($allowed)));
        }

        throw HttpException::notFound();
    }

    /**
     * «/libros/{id}» pasa a ser «#^/libros/(?P<id>[^/]+)$#»: cada {nombre} toma un segmento
     * entero y lo demás se compara literal.
     *
     * @param array{0: class-string, 1: string} $action
     */
    private function add(string $method, string $pattern, array $action): void
    {
        $regex = preg_replace_callback(
            '/\{(\w+)\}|[^{]+/',
            static fn (array $part): string => ($part[1] ?? '') !== ''
                ? "(?P<{$part[1]}>[^/]+)"
                : preg_quote($part[0], '#'),
            $pattern,
        );

        $this->routes[] = ['method' => $method, 'regex' => "#^{$regex}$#", 'action' => $action];
    }
}
