<?php

declare(strict_types=1);

namespace PawPrints\Core;

use InvalidArgumentException;

/**
 * Un correo de texto plano, listo para que lo mande un Mailer. El remitente no va acá: es del
 * sitio y lo pone el Mailer desde la configuración, así quien arma el correo sólo dice a quién y
 * qué.
 */
final class Email
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $toName = '',
        public readonly string $replyTo = '',
        public readonly string $replyToName = '',
    ) {
        // Una cabecera termina en el salto de línea: un dato del cliente con uno adentro podría
        // agregar cabeceras propias (un Bcc a cualquiera). La reserva ya lo valida; esto es la red.
        foreach ([$to, $subject, $toName, $replyTo, $replyToName] as $value) {
            if (preg_match('/[\r\n]/', $value) === 1) {
                throw new InvalidArgumentException('Una cabecera de correo no puede tener saltos de línea.');
            }
        }
    }

    /**
     * El mensaje entero como lo pide RFC 5322: las cabeceras, una línea en blanco y el cuerpo, con
     * CRLF al final de cada línea.
     */
    public function toMime(string $from, string $fromName): string
    {
        $domain = substr((string) strrchr($from, '@'), 1) ?: 'localhost';

        $headers = [
            'Date' => date(DATE_RFC2822),
            'From' => self::address($from, $fromName),
            'To' => self::address($this->to, $this->toName),
        ];
        if ($this->replyTo !== '') {
            $headers['Reply-To'] = self::address($this->replyTo, $this->replyToName);
        }
        $headers += [
            'Subject' => self::encode($this->subject),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . "@{$domain}>",
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Quoted-printable deja legible el texto en castellano y corta las líneas a 76
            // caracteres, como piden los servidores de correo.
            'Content-Transfer-Encoding' => 'quoted-printable',
        ];

        $mime = '';
        foreach ($headers as $name => $value) {
            $mime .= "{$name}: {$value}\r\n";
        }

        // quoted_printable_encode() sólo respeta los saltos CRLF: un \n suelto lo codifica.
        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $this->body);

        return $mime . "\r\n" . quoted_printable_encode($body);
    }

    /** «Juan Pérez <juan@ejemplo.com>», o sólo la dirección si no hay nombre. */
    private static function address(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }

        $phrase = self::isAscii($name) ? '"' . addcslashes($name, '"\\') . '"' : self::encode($name);

        return "{$phrase} <{$email}>";
    }

    /**
     * Las cabeceras sólo admiten ASCII: lo demás va en «palabras codificadas» de RFC 2047, de
     * hasta 75 caracteres cada una.
     */
    private static function encode(string $text): string
    {
        if (self::isAscii($text)) {
            return $text;
        }

        // 45 bytes son 60 en base64, que con «=?UTF-8?B?» y «?=» llegan a 72. Se corta por
        // caracteres y no por bytes, para no partir una letra con tilde entre dos palabras.
        $chunks = [''];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen(end($chunks) . $char) > 45) {
                $chunks[] = '';
            }
            $chunks[array_key_last($chunks)] .= $char;
        }

        return implode("\r\n ", array_map(
            static fn (string $chunk): string => '=?UTF-8?B?' . base64_encode($chunk) . '?=',
            $chunks,
        ));
    }

    private static function isAscii(string $text): bool
    {
        return preg_match('/^[\x20-\x7E]*$/', $text) === 1;
    }
}
