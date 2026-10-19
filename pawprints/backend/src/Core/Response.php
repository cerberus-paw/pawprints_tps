<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * La respuesta HTTP: estado, cabeceras y cuerpo. Los controladores la devuelven en lugar de hacer
 * echo y header() por su cuenta, así nada se envía antes de tiempo y App puede registrarla y
 * reemplazarla por una de error si algo falla.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $text, int $status = 200, array $headers = []): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=utf-8'] + $headers);
    }

    /** Después de un POST va 303, para que recargar la página no reenvíe el formulario. */
    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /** Un archivo para guardar en lugar de mostrar, como el CSV del catálogo. */
    public static function download(string $content, string $contentType, string $filename): self
    {
        // El nombre va entre comillas en la cabecera: sin comillas ni saltos de línea que la corten.
        $filename = str_replace(['"', "\r", "\n"], '', $filename);

        return new self($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function withBody(string $body): self
    {
        return new self($body, $this->status, $this->headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
