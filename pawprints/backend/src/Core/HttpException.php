<?php

declare(strict_types=1);

namespace PawPrints\Core;

use RuntimeException;

/**
 * Un error que ya sabe con qué estado HTTP responder. Un controlador la lanza cuando, por ejemplo,
 * piden un libro o una página del catálogo que no existe: `throw HttpException::notFound();`.
 */
final class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    /**
     * No es un error: el pedido sigue en otra página, como el ingreso cuando falta la sesión. Va
     * por excepción para que cualquier método del controlador pueda cortar el pedido ahí mismo.
     */
    public static function redirect(string $location): self
    {
        return new self(303, 'Sigue en otra página.', ['Location' => $location]);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function notFound(string $message = 'Página no encontrada.'): self
    {
        return new self(404, $message);
    }

    /**
     * @param list<string> $allowed
     */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'Método no permitido.', ['Allow' => implode(', ', $allowed)]);
    }
}
