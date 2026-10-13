<?php

declare(strict_types=1);

namespace PawPrints\Core;

use InvalidArgumentException;
use Stringable;
use Throwable;

/**
 * Los registros de la aplicación, en logs/app.log (fuera de la raíz pública: puede tener rutas y
 * trazas que no tiene que ver nadie de afuera).
 *
 * Sigue la interfaz de PSR-3 (los mismos ocho niveles, los mismos métodos y los {marcadores} del
 * mensaje) sin depender del paquete psr/log: si más adelante se cambia por Monolog, quien lo usa
 * no se entera.
 */
final class Logger
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    private readonly int $minLevel;

    /**
     * @param string $minLevel lo que está por debajo no se escribe: debug en desarrollo, info o
     *                         warning en producción
     */
    public function __construct(private readonly string $file, string $minLevel = 'debug')
    {
        $this->minLevel = self::rank($minLevel);
    }

    /** @param array<string, mixed> $context */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        if (self::rank($level) < $this->minLevel) {
            return;
        }

        $line = sprintf(
            '[%s] %s %s',
            date(DATE_ATOM),
            strtoupper($level),
            self::escape(self::interpolate((string) $message, $context)),
        );

        // La traza ocupa varias líneas: van con sangría para que se vea a qué entrada pertenecen.
        $exception = $context['exception'] ?? null;
        if ($exception instanceof Throwable) {
            $line .= "\n    " . str_replace("\n", "\n    ", (string) $exception);
        }

        // Si no se puede escribir (permisos en el servidor), va al log de PHP: ni se pierde ni
        // rompe el pedido.
        if (@file_put_contents($this->file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }

    private static function rank(string $level): int
    {
        return self::LEVELS[strtolower($level)]
            ?? throw new InvalidArgumentException("Nivel de log desconocido: {$level}.");
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function interpolate(string $message, array $context): string
    {
        return preg_replace_callback('/\{([\w.]+)\}/', static function (array $match) use ($context): string {
            $value = $context[$match[1]] ?? null;

            return match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_scalar($value), $value instanceof Stringable => (string) $value,
                default => $match[0],
            };
        }, $message) ?? $message;
    }

    /**
     * Un valor que vino del cliente (la ruta pedida, por ejemplo) no puede meter un salto de línea
     * e inventar una entrada que parezca otra.
     */
    private static function escape(string $text): string
    {
        return addcslashes($text, "\0..\37\177");
    }
}
