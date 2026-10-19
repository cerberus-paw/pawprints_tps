<?php

declare(strict_types=1);

namespace PawPrints\Models;

/**
 * Una reserva ya validada. No se guarda: se le manda por correo al personal, que es quien la
 * confirma y aparta los libros.
 *
 * La única forma de crearla es desdeFormulario(), así que una Reserva que existe es válida.
 */
final class Reserva
{
    public const MAX_EJEMPLARES = 5;

    private const MAX_NOMBRE = 100;
    private const MAX_EMAIL = 254;
    private const MAX_COMENTARIOS = 500;

    /**
     * @param array{id: int, titulo: string, autor: string, precio: int} $libro
     */
    private function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $telefono,
        public readonly array $libro,
        public readonly int $cantidad,
        public readonly string $comentarios,
    ) {
    }

    /**
     * Valida lo que llegó del formulario. El navegador ya valida con los atributos del HTML, pero
     * eso se saltea mandando el POST a mano: acá se vuelve a validar todo.
     *
     * @param array<string, string> $campos nombre, email, telefono, libro_id, cantidad y comentarios
     *
     * @throws ReservaInvalida con un mensaje por cada campo que no pasó
     */
    public static function desdeFormulario(array $campos, CatalogoDeLibros $catalogo): self
    {
        $errores = [];

        // UTF-8 mal formado no es texto: no se puede medir, mostrar ni mandar por correo.
        foreach ($campos as $campo => $valor) {
            if (preg_match('//u', $valor) !== 1) {
                $errores[$campo] = 'Tiene caracteres que no se pueden leer.';
                $campos[$campo] = '';
            }
        }

        // Los espacios de más (y cualquier salto de línea) se juntan en uno: el nombre va en una
        // cabecera del correo, donde un salto de línea permitiría agregar cabeceras propias.
        $nombre = trim((string) preg_replace('/\s+/u', ' ', $campos['nombre'] ?? ''));
        if ($nombre === '') {
            $errores['nombre'] ??= 'Escribí tu nombre.';
        } elseif (self::largo($nombre) > self::MAX_NOMBRE) {
            $errores['nombre'] = 'El nombre puede tener hasta ' . self::MAX_NOMBRE . ' caracteres.';
        } elseif (preg_match('/\p{Cc}/u', $nombre) === 1) {
            $errores['nombre'] = 'El nombre tiene caracteres que no se pueden usar.';
        }

        // FILTER_VALIDATE_EMAIL tampoco deja pasar saltos de línea.
        $email = trim($campos['email'] ?? '');
        if ($email === '') {
            $errores['email'] ??= 'Escribí tu correo electrónico.';
        } elseif (strlen($email) > self::MAX_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'Escribí un correo válido, como juan.perez@ejemplo.com.';
        }

        // El mismo patrón que el atributo pattern del HTML, y además entre 7 y 15 dígitos (el
        // máximo de un número internacional): el patrón solo deja pasar «-------».
        $telefono = trim($campos['telefono'] ?? '');
        $digitos = preg_match_all('/\d/', $telefono);
        if ($telefono === '') {
            $errores['telefono'] ??= 'Escribí un teléfono de contacto.';
        } elseif (preg_match('/^\+?[\d\s\-()]{7,20}$/', $telefono) !== 1 || $digitos < 7 || $digitos > 15) {
            $errores['telefono'] = 'Escribí un teléfono con código de área, como 2324 12-3456.';
        }

        // El id tiene que ser de un libro que existe: el desplegable se puede reemplazar a mano.
        $libro = null;
        $libroId = filter_var($campos['libro_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (($campos['libro_id'] ?? '') === '') {
            $errores['libro_id'] ??= 'Elegí el libro que querés reservar.';
        } elseif ($libroId === false || ($libro = $catalogo->porId($libroId)) === null) {
            $errores['libro_id'] = 'Ese libro no está en el catálogo. Elegí uno de la lista.';
        }

        $cantidad = filter_var(trim($campos['cantidad'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_EJEMPLARES],
        ]);
        if ($cantidad === false) {
            $errores['cantidad'] ??= 'La cantidad tiene que ser un número entero entre 1 y ' . self::MAX_EJEMPLARES . '.';
        }

        // El navegador manda los saltos de línea del textarea como CRLF, pero su maxlength los
        // cuenta como un carácter: se pasan a \n antes de medir, para que coincidan los límites.
        $comentarios = trim(str_replace("\r\n", "\n", $campos['comentarios'] ?? ''));
        if (self::largo($comentarios) > self::MAX_COMENTARIOS) {
            $errores['comentarios'] = 'Los comentarios pueden tener hasta ' . self::MAX_COMENTARIOS . ' caracteres.';
        } elseif (preg_match('/[^\P{Cc}\n\t]/u', $comentarios) === 1) {
            $errores['comentarios'] = 'Los comentarios tienen caracteres que no se pueden usar.';
        }

        if ($errores !== []) {
            throw new ReservaInvalida($errores);
        }

        return new self($nombre, $email, $telefono, $libro, $cantidad, $comentarios);
    }

    public function total(): int
    {
        return $this->libro['precio'] * $this->cantidad;
    }

    /** En caracteres y no en bytes, como cuenta el maxlength del navegador: «ñ» es uno solo. */
    private static function largo(string $texto): int
    {
        return (int) preg_match_all('/./su', $texto);
    }
}
