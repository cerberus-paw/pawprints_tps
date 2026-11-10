<?php

declare(strict_types=1);

namespace PawPrints\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * La conexión a la base de datos, con los datos de la sección [db] de config.ini. Se conecta
 * recién la primera vez que un repositorio pide el PDO y una sola vez por pedido: la portada y
 * «Sobre nosotros» no usan la base, así que siguen andando aunque la base esté caída.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->conectar();
    }

    /**
     * Si la sección falta o la base no responde, la excepción sigue el camino de cualquier otro
     * error: el cliente ve el 500 genérico y el motivo queda en el log. El mensaje nombra el
     * servidor y la base, nunca la clave, y la traza no la muestra porque ErrorHandler saca los
     * argumentos de las llamadas.
     */
    private function conectar(): PDO
    {
        $base = (string) $this->config->get('db.base', '');
        $usuario = (string) $this->config->get('db.usuario', '');
        if ($base === '' || $usuario === '') {
            throw new RuntimeException('Faltan db.base o db.usuario en config.ini: ver la sección [db] de config.example.ini.');
        }

        $servidor = (string) $this->config->get('db.servidor', '127.0.0.1');
        $puerto = (int) $this->config->get('db.puerto', 3306);
        // utf8mb4 en la conexión y no sólo en las tablas: si no, las tildes y las ñ viajan en
        // otra codificación y llegan rotas a la página.
        $dsn = "mysql:host={$servidor};port={$puerto};dbname={$base};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $usuario, (string) $this->config->get('db.clave', ''), [
                // Un error de SQL es una excepción y no un false que alguien se olvida de mirar.
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Preparadas de verdad en el servidor: la consulta y los datos viajan por separado,
                // que es lo que protege de la inyección SQL (TP5, consigna 5), y los enteros y
                // decimales vuelven con su tipo y no como texto.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $error) {
            throw new RuntimeException("No se pudo conectar a la base {$base} en {$servidor}:{$puerto}.", 0, $error);
        }

        // Las fechas que pone la base (la de una reserva, la del alta de un libro) en la hora del
        // sitio, app.zona_horaria, y no en la del servidor de MySQL, que suele estar en UTC. Va
        // como diferencia («-03:00») y no por nombre porque los nombres de zona necesitan tablas
        // que MySQL no trae cargadas.
        $pdo->prepare('SET time_zone = ?')->execute([date('P')]);

        return $pdo;
    }
}
