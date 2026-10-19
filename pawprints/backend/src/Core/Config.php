<?php

declare(strict_types=1);

namespace PawPrints\Core;

use RuntimeException;

/**
 * La configuración de config/config.ini: lo único que cambia entre una máquina y el servidor
 * publicado. El código pide los valores por «sección.clave» y nunca los tiene escritos.
 */
final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $values)
    {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Falta {$path}: se crea copiando config.example.ini.");
        }

        // Con el @, un .ini mal escrito no imprime el aviso de PHP en la página: llega a la excepción.
        $values = @parse_ini_file($path, true, INI_SCANNER_TYPED);
        if ($values === false) {
            throw new RuntimeException("{$path} no es un .ini válido: " . trim(error_get_last()['message'] ?? ''));
        }

        return new self($values);
    }

    /** Un valor por «sección.clave», como «correo.reservas»; sin el punto, la sección entera. */
    public function get(string $key, mixed $default = null): mixed
    {
        [$section, $name] = array_pad(explode('.', $key, 2), 2, null);
        $values = $this->values[$section] ?? null;

        if ($name === null) {
            return $values ?? $default;
        }

        return is_array($values) && array_key_exists($name, $values) ? $values[$name] : $default;
    }

    /**
     * Sólo un «desarrollo» explícito sale de producción: una clave que falta o mal escrita no
     * puede terminar mostrando el detalle de un error.
     */
    public function inProduction(): bool
    {
        return $this->get('app.entorno') !== 'desarrollo';
    }
}
