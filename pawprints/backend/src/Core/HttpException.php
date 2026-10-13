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
