<?php

declare(strict_types=1);

namespace PawPrints\Models;

use DomainException;

/**
 * Lo que lanza Reserva cuando el formulario tiene errores, con un mensaje por campo para
 * mostrarlo al lado de cada uno.
 */
final class ReservaInvalida extends DomainException
{
    /**
     * @param array<string, string> $errores campo del formulario => mensaje para el usuario
     */
    public function __construct(public readonly array $errores)
    {
        parent::__construct('La reserva tiene errores: ' . implode(', ', array_keys($errores)) . '.');
    }
}
