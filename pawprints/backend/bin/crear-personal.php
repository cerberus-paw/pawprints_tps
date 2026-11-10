<?php

declare(strict_types=1);

/*
 * Alta de una persona del personal, que después entra en /personal/ingresar para ver los pedidos.
 * Es la única forma de crear un usuario: el sitio no tiene registro público, porque los pedidos
 * tienen los datos de los clientes. Si el usuario ya existe, le cambia la contraseña, así que
 * sirve también para recuperarla.
 *
 *   php pawprints/backend/bin/crear-personal.php
 *
 * Pide el usuario, el nombre y la contraseña por la terminal, sin mostrar la contraseña. Se
 * conecta con la sección [db] de config.ini, como el sitio. En la base queda sólo el hash de
 * password_hash(): la contraseña no se guarda en ningún lado.
 */

use PawPrints\Core\Config;
use PawPrints\Core\Database;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../bootstrap.php';

const MIN_CLAVE = 8;

$backend = dirname(__DIR__);
$config = Config::fromFile($backend . '/config/config.ini');
date_default_timezone_set((string) $config->get('app.zona_horaria', 'UTC'));
$pdo = (new Database($config))->pdo();

/** Una línea de la terminal, sin el salto. Con $oculta, lo que se escribe no se ve. */
function preguntar(string $pregunta, bool $oculta = false): string
{
    echo $pregunta;
    // Sólo en una terminal de verdad: con la entrada redirigida (un archivo, un pipe) no hay nada
    // que ocultar, y stty fallaría.
    $ocultar = $oculta && stream_isatty(STDIN);
    if ($ocultar) {
        shell_exec('stty -echo');
    }
    $linea = fgets(STDIN);
    if ($ocultar) {
        shell_exec('stty echo');
        echo PHP_EOL;
    }
    if ($linea === false) {
        fwrite(STDERR, PHP_EOL . 'Se cortó la entrada: no se guardó nada.' . PHP_EOL);
        exit(1);
    }

    return trim($linea);
}

function error(string $mensaje): never
{
    fwrite(STDERR, $mensaje . PHP_EOL);
    exit(1);
}

// Letras sin tildes, números, punto, guion y guion bajo: el usuario se escribe en el formulario de
// ingreso y aparece en el log, y así no hay dos que se vean iguales y sean distintos.
$usuario = strtolower(preguntar('Usuario: '));
if (preg_match('/^[a-z0-9._-]{3,50}$/', $usuario) !== 1) {
    error('El usuario tiene que tener de 3 a 50 letras sin tildes, números, «.», «-» o «_».');
}

$consulta = $pdo->prepare('SELECT nombre FROM personal WHERE usuario = ?');
$consulta->execute([$usuario]);
$actual = $consulta->fetchColumn();

if ($actual !== false) {
    echo "«{$usuario}» ya existe ({$actual}): se le cambia la contraseña." . PHP_EOL;
    $nombre = (string) $actual;
} else {
    $nombre = preguntar('Nombre para mostrar: ');
    if ($nombre === '' || mb_strlen($nombre) > 100) {
        error('El nombre no puede estar vacío ni pasar de 100 caracteres.');
    }
}

$clave = preguntar('Contraseña: ', true);
if (mb_strlen($clave) < MIN_CLAVE) {
    error('La contraseña tiene que tener al menos ' . MIN_CLAVE . ' caracteres.');
}
if (preguntar('Repetila: ', true) !== $clave) {
    error('Las contraseñas no coinciden: no se guardó nada.');
}

// PASSWORD_DEFAULT: hoy bcrypt, y el que PHP recomiende mañana. El ingreso usa
// password_needs_rehash() para actualizar los hashes viejos cuando cambie.
$pdo->prepare(
    'INSERT INTO personal (usuario, nombre, clave_hash) VALUES (:usuario, :nombre, :hash) AS nuevo'
    . ' ON DUPLICATE KEY UPDATE clave_hash = nuevo.clave_hash',
)->execute([
    'usuario' => $usuario,
    'nombre' => $nombre,
    'hash' => password_hash($clave, PASSWORD_DEFAULT),
]);

echo ($actual !== false ? 'Contraseña cambiada' : 'Usuario creado') . ": {$usuario}." . PHP_EOL;
