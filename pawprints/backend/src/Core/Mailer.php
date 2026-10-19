<?php

declare(strict_types=1);

namespace PawPrints\Core;

use RuntimeException;

/**
 * El envío de correos. Qué transporte se usa lo decide config.ini (correo.transporte): en
 * desarrollo FileMailer los guarda como archivos y en el servidor SmtpMailer los manda de verdad.
 * Quien manda un correo no sabe cuál de los dos tiene.
 */
interface Mailer
{
    /**
     * @throws RuntimeException si el correo no salió: quien lo manda decide qué le dice al usuario
     */
    public function send(Email $email): void;
}
