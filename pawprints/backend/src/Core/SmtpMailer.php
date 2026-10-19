<?php

declare(strict_types=1);

namespace PawPrints\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Manda los correos por SMTP (RFC 5321), con STARTTLS y AUTH LOGIN. Está hecho a mano porque el
 * TP3 no tiene dependencias de terceros; mail() no sirve en su lugar porque depende de que el
 * servidor tenga sendmail configurado, y en la mayoría de los hostings no lo tiene.
 *
 * Con cualquier cuenta que dé SMTP alcanza; con Gmail, por ejemplo, smtp.gmail.com, puerto 587,
 * «tls» y una contraseña de aplicación.
 */
final class SmtpMailer implements Mailer
{
    private const SECURITY = ['tls', 'ssl', 'ninguna'];

    /** @var resource|null */
    private $socket = null;

    /**
     * @param string $security «tls» (STARTTLS, puerto 587), «ssl» (TLS desde el principio, puerto
     *                         465) o «ninguna» (sólo para un servidor de pruebas local)
     * @param string $user     vacío si el servidor no pide autenticarse
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $security,
        private readonly string $user,
        private readonly string $password,
        private readonly string $from,
        private readonly string $fromName,
        private readonly int $timeout = 10,
    ) {
        if (!in_array($security, self::SECURITY, true)) {
            throw new InvalidArgumentException("Seguridad SMTP desconocida: {$security}.");
        }
    }

    public function send(Email $email): void
    {
        $this->connect();
        try {
            $this->expect(220, 'la conexión');
            $this->hello();

            if ($this->security === 'tls') {
                $this->command('STARTTLS', 220);
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                if (@stream_socket_enable_crypto($this->socket, true, $crypto) !== true) {
                    throw new RuntimeException("No se pudo cifrar la conexión con {$this->host}.");
                }
                // Después de STARTTLS el servidor olvida el saludo anterior (RFC 3207).
                $this->hello();
            }

            if ($this->user !== '') {
                // El usuario y la clave no van en el mensaje de error: terminaría en el log.
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->user), 334, 'el usuario');
                $this->command(base64_encode($this->password), 235, 'la clave');
            }

            $this->command("MAIL FROM:<{$this->from}>", 250);
            $this->command("RCPT TO:<{$email->to}>", [250, 251]);
            $this->command('DATA', 354);
            // Una línea que empieza con punto se duplica: un punto solo en su línea es el fin del
            // mensaje. Ese punto final lo agrega command(), que cierra la línea con CRLF.
            $this->command(
                preg_replace('/^\./m', '..', $email->toMime($this->from, $this->fromName)) . "\r\n.",
                250,
                'el mensaje',
            );
            $this->command('QUIT', 221);
        } finally {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function connect(): void
    {
        $scheme = $this->security === 'ssl' ? 'ssl' : 'tcp';
        $context = stream_context_create(['ssl' => ['peer_name' => $this->host]]);

        $socket = @stream_socket_client(
            "{$scheme}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($socket === false) {
            throw new RuntimeException("No se pudo conectar a {$this->host}:{$this->port}: {$errstr}");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function hello(): void
    {
        $this->command('EHLO ' . (gethostname() ?: 'localhost'), 250);
    }

    /**
     * @param int|list<int> $expected
     * @param string|null   $what     cómo nombrar el comando en el error, si no se puede mostrar
     */
    private function command(string $line, int|array $expected, ?string $what = null): void
    {
        if (@fwrite($this->socket, $line . "\r\n") === false) {
            throw new RuntimeException("Se cortó la conexión con {$this->host}.");
        }

        $this->expect($expected, $what ?? strtok($line, ' '));
    }

    /**
     * @param int|list<int> $expected
     */
    private function expect(int|array $expected, string $what): void
    {
        $reply = '';
        // Una respuesta puede ocupar varias líneas: «250-…» anuncia que siguen, «250 …» es la última.
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        if ($reply === '') {
            $why = stream_get_meta_data($this->socket)['timed_out'] ? "no respondió en {$this->timeout} s" : 'cerró la conexión';
            throw new RuntimeException("{$this->host} {$why} después de {$what}.");
        }

        if (!in_array((int) substr($reply, 0, 3), (array) $expected, true)) {
            throw new RuntimeException("{$this->host} rechazó {$what}: " . trim($reply));
        }
    }
}
