<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;

/**
 * Las reservas hechas desde el sitio, en la tabla reservas (TP5, consigna 2). Hasta el TP4 sólo
 * llegaban por correo: si el correo se perdía, se perdía la reserva.
 */
final class ReservaRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Guarda una reserva ya validada y devuelve su número, el que va en el asunto de los correos.
     * Queda con el aviso al personal sin enviar hasta que avisoEnviado() diga lo contrario. La
     * fecha la pone la base.
     */
    public function guardar(Reserva $reserva): int
    {
        $pdo = $this->db->pdo();
        $consulta = $pdo->prepare(
            'INSERT INTO reservas (libro_id, cantidad, nombre, correo, telefono, comentarios)'
            . ' VALUES (:libro, :cantidad, :nombre, :correo, :telefono, :comentarios)',
        );
        $consulta->execute([
            'libro' => $reserva->libro['id'],
            'cantidad' => $reserva->cantidad,
            'nombre' => $reserva->nombre,
            'correo' => $reserva->email,
            'telefono' => $reserva->telefono,
            'comentarios' => $reserva->comentarios,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** Marca que el aviso de esa reserva le llegó al personal. */
    public function avisoEnviado(int $numero): void
    {
        $this->db->pdo()
            ->prepare('UPDATE reservas SET aviso_enviado = TRUE WHERE id = ?')
            ->execute([$numero]);
    }
}
