<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;

/**
 * Quienes atienden la librería y entran a ver los pedidos (TP5, consigna 3), en la tabla
 * personal. Se dan de alta con bin/crear-personal.php.
 */
final class PersonalRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{id: int, usuario: string, nombre: string, clave_hash: string}|null
     */
    public function porUsuario(string $usuario): ?array
    {
        $consulta = $this->db->pdo()->prepare(
            'SELECT id, usuario, nombre, clave_hash FROM personal WHERE usuario = ?',
        );
        $consulta->execute([$usuario]);
        $persona = $consulta->fetch();

        /** @var array{id: int, usuario: string, nombre: string, clave_hash: string}|null */
        return $persona === false ? null : $persona;
    }

    /** Reemplaza el hash de la contraseña, cuando PHP cambia el algoritmo recomendado. */
    public function cambiarHash(int $id, string $hash): void
    {
        $this->db->pdo()
            ->prepare('UPDATE personal SET clave_hash = ? WHERE id = ?')
            ->execute([$hash, $id]);
    }
}
