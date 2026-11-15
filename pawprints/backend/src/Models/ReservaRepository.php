<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;
use PDO;

/**
 * Las reservas hechas desde el sitio, en las tablas reservas y reserva_libros (TP5, consigna 2,
 * y el carrito de TP5-11). Hasta el TP4 sólo llegaban por correo: si el correo se perdía, se
 * perdía la reserva.
 */
final class ReservaRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Guarda una reserva ya validada con sus libros y devuelve su número, el que va en el asunto
     * de los correos. Queda con el aviso al personal sin enviar hasta que avisoEnviado() diga lo
     * contrario. La fecha la pone la base.
     *
     * Va en una transacción: si falla un renglón, no queda una reserva sin libros (o con una
     * parte) que el personal vería en su listado.
     */
    public function guardar(Reserva $reserva): int
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO reservas (nombre, correo, telefono, comentarios)'
                . ' VALUES (:nombre, :correo, :telefono, :comentarios)',
            )->execute([
                'nombre' => $reserva->nombre,
                'correo' => $reserva->email,
                'telefono' => $reserva->telefono,
                'comentarios' => $reserva->comentarios,
            ]);
            $numero = (int) $pdo->lastInsertId();

            // El precio se copia del libro: es el que dice el correo que se le manda al cliente.
            $renglon = $pdo->prepare(
                'INSERT INTO reserva_libros (reserva_id, libro_id, cantidad, precio)'
                . ' VALUES (:reserva, :libro, :cantidad, :precio)',
            );
            foreach ($reserva->renglones as $elegido) {
                $renglon->execute([
                    'reserva' => $numero,
                    'libro' => $elegido['libro']['id'],
                    'cantidad' => $elegido['cantidad'],
                    'precio' => $elegido['libro']['precio'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        return $numero;
    }

    /**
     * Una página del listado del personal, de la más nueva a la más vieja, cada una con sus libros
     * y el total.
     *
     * Son dos consultas: la de las reservas lleva el LIMIT del paginado, que con un JOIN a sus
     * libros contaría renglones y no reservas; la segunda trae los libros de esas reservas nada más.
     *
     * @return list<array{id: int, creada: string, nombre: string, correo: string, telefono: string,
     *                    comentarios: string, aviso_enviado: int, total: int,
     *                    libros: list<array{libro_id: int, titulo: string, autor: string,
     *                                       cantidad: int, precio: int}>}>
     */
    public function listar(int $pagina, int $porPagina): array
    {
        $pdo = $this->db->pdo();
        $consulta = $pdo->prepare(
            'SELECT id, creada, nombre, correo, telefono, comentarios, aviso_enviado FROM reservas'
            // El id desempata las que llegaron en el mismo segundo.
            . ' ORDER BY creada DESC, id DESC LIMIT :limite OFFSET :desde',
        );
        // Enteros: con las preparadas de verdad, MySQL no acepta un texto en el LIMIT.
        $consulta->bindValue('limite', max(1, $porPagina), PDO::PARAM_INT);
        $consulta->bindValue('desde', max(0, ($pagina - 1) * $porPagina), PDO::PARAM_INT);
        $consulta->execute();
        $reservas = $consulta->fetchAll();
        if ($reservas === []) {
            return [];
        }

        // Un signo de pregunta por reserva: los ids van como parámetros, no pegados en el SQL.
        $ids = array_column($reservas, 'id');
        $libros = $pdo->prepare(
            'SELECT rl.reserva_id, rl.libro_id, rl.cantidad, rl.precio, l.titulo, l.autor'
            . ' FROM reserva_libros rl JOIN libros l ON l.id = rl.libro_id'
            . ' WHERE rl.reserva_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')'
            . ' ORDER BY l.titulo',
        );
        $libros->execute($ids);
        $porReserva = [];
        foreach ($libros->fetchAll() as $libro) {
            $porReserva[$libro['reserva_id']][] = $libro;
        }

        foreach ($reservas as &$reserva) {
            $reserva['libros'] = $porReserva[$reserva['id']] ?? [];
            $reserva['total'] = array_sum(array_map(
                static fn (array $libro): int => (int) $libro['precio'] * (int) $libro['cantidad'],
                $reserva['libros'],
            ));
        }
        unset($reserva);

        /** @var list<array{id: int, creada: string, nombre: string, correo: string, telefono: string, comentarios: string, aviso_enviado: int, total: int, libros: list<array{libro_id: int, titulo: string, autor: string, cantidad: int, precio: int}>}> */
        return $reservas;
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
