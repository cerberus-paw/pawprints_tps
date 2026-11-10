<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;
use PDO;

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

    /**
     * Una página del listado del personal, de la más nueva a la más vieja, con el título y el
     * autor del libro de cada una.
     *
     * @return list<array{id: int, creada: string, cantidad: int, nombre: string, correo: string,
     *                    telefono: string, comentarios: string, aviso_enviado: int,
     *                    libro_id: int, titulo: string, autor: string}>
     */
    public function listar(int $pagina, int $porPagina): array
    {
        $consulta = $this->db->pdo()->prepare(
            'SELECT r.id, r.creada, r.cantidad, r.nombre, r.correo, r.telefono, r.comentarios,'
            . ' r.aviso_enviado, r.libro_id, l.titulo, l.autor'
            . ' FROM reservas r JOIN libros l ON l.id = r.libro_id'
            // El id desempata las que llegaron en el mismo segundo.
            . ' ORDER BY r.creada DESC, r.id DESC LIMIT :limite OFFSET :desde',
        );
        // Enteros: con las preparadas de verdad, MySQL no acepta un texto en el LIMIT.
        $consulta->bindValue('limite', max(1, $porPagina), PDO::PARAM_INT);
        $consulta->bindValue('desde', max(0, ($pagina - 1) * $porPagina), PDO::PARAM_INT);
        $consulta->execute();

        /** @var list<array{id: int, creada: string, cantidad: int, nombre: string, correo: string, telefono: string, comentarios: string, aviso_enviado: int, libro_id: int, titulo: string, autor: string}> */
        return $consulta->fetchAll();
    }

    public function contar(): int
    {
        return (int) $this->db->pdo()->query('SELECT COUNT(*) FROM reservas')->fetchColumn();
    }

    /** Marca que el aviso de esa reserva le llegó al personal. */
    public function avisoEnviado(int $numero): void
    {
        $this->db->pdo()
            ->prepare('UPDATE reservas SET aviso_enviado = TRUE WHERE id = ?')
            ->execute([$numero]);
    }
}
