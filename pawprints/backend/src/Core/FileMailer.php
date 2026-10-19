<?php

declare(strict_types=1);

namespace PawPrints\Core;

use RuntimeException;

/**
 * El Mailer de desarrollo: en lugar de mandar cada correo lo guarda como .eml en una carpeta de
 * backend/, que se abre con cualquier cliente de correo. Así la reserva se prueba sin
 * credenciales ni conexión, y sin llenarle la casilla a nadie.
 */
final class FileMailer implements Mailer
{
    public function __construct(
        private readonly string $dir,
        private readonly string $from,
        private readonly string $fromName,
    ) {
    }

    public function send(Email $email): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new RuntimeException("No se pudo crear {$this->dir}.");
        }

        // La fecha adelante, para que el más nuevo quede último al listar la carpeta.
        $file = sprintf('%s/%s-%s.eml', $this->dir, date('Ymd-His'), bin2hex(random_bytes(3)));
        if (@file_put_contents($file, $email->toMime($this->from, $this->fromName)) === false) {
            throw new RuntimeException("No se pudo escribir {$file}.");
        }
    }
}
