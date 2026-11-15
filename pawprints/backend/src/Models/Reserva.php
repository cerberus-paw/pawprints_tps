<?php

declare(strict_types=1);

namespace PawPrints\Models;

/**
 * Una reserva ya validada: los datos del cliente y uno o más libros, cada uno con su cantidad. Se
 * guarda en la base y se le avisa por correo al personal, que es quien aparta los libros.
 *
 * Hasta el TP5 era de un solo libro. Con el carrito (TP5-11) pasó a una lista de renglones: el
 * formulario sin JavaScript sigue mandando uno, y el carrito manda todos los suyos.
 *
 * La única forma de crearla es desdeFormulario(), así que una Reserva que existe es válida.
 */
final class Reserva
{
    /** Ejemplares de un mismo libro en una reserva. */
    public const MAX_EJEMPLARES = 5;

    /** Libros distintos en una reserva: el carrito no deja agregar más. */
    public const MAX_LIBROS = 10;

    private const MAX_NOMBRE = 100;
    private const MAX_EMAIL = 254;
    private const MAX_COMENTARIOS = 500;

    /**
     * @param non-empty-list<array{libro: array{id: int, titulo: string, autor: string, precio: int, stock?: int}, cantidad: int}> $renglones
     */
    private function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $telefono,
        public readonly array $renglones,
        public readonly string $comentarios,
    ) {
    }

    /**
     * Valida lo que llegó del formulario. El navegador ya valida con los atributos del HTML, pero
     * eso se saltea mandando el POST a mano: acá se vuelve a validar todo.
     *
     * Los errores de cada renglón van con el id de su control («libro-0», «cantidad-1»), así el
     * resumen de errores enlaza al campo y la vista lo marca con las mismas macros de siempre.
     *
     * @param array<string, string> $campos nombre, email, telefono y comentarios
     * @param list<array{libro_id: string, cantidad: string}> $renglones lo que eligió, por orden
     *
     * @throws ReservaInvalida con un mensaje por cada campo que no pasó
     */
    public static function desdeFormulario(array $campos, array $renglones, CatalogoDeLibros $catalogo): self
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

        [$elegidos, $erroresLibros] = self::validarRenglones($renglones, $catalogo);
        $errores += $erroresLibros;

        // El navegador manda los saltos de línea del textarea como CRLF, pero su maxlength los
        // cuenta como un carácter: se pasan a \n antes de medir, para que coincidan los límites.
        $comentarios = trim(str_replace("\r\n", "\n", $campos['comentarios'] ?? ''));
        if (self::largo($comentarios) > self::MAX_COMENTARIOS) {
            $errores['comentarios'] = 'Los comentarios pueden tener hasta ' . self::MAX_COMENTARIOS . ' caracteres.';
        } elseif (preg_match('/[^\P{Cc}\n\t]/u', $comentarios) === 1) {
            $errores['comentarios'] = 'Los comentarios tienen caracteres que no se pueden usar.';
        }

        if ($errores !== [] || $elegidos === []) {
            throw new ReservaInvalida($errores);
        }

        return new self($nombre, $email, $telefono, $elegidos, $comentarios);
    }

    /**
     * Los renglones elegidos, con el libro del catálogo, o los errores de cada uno.
     *
     * Un renglón sin libro elegido se saltea (es el que quedó vacío en el formulario), salvo que
     * sean todos. Un libro repetido en dos renglones es un error y no se suma: así el tope de
     * ejemplares de cada libro no se esquiva partiéndolo en dos.
     *
     * @param list<array{libro_id: string, cantidad: string}> $renglones
     *
     * @return array{list<array{libro: array{id: int, titulo: string, autor: string, precio: int, stock?: int}, cantidad: int}>, array<string, string>}
     */
    private static function validarRenglones(array $renglones, CatalogoDeLibros $catalogo): array
    {
        $errores = [];
        $elegidos = [];
        $vistos = [];

        foreach ($renglones as $i => $renglon) {
            $id = trim($renglon['libro_id']);
            if ($id === '') {
                continue;
            }

            // El id tiene que ser de un libro que existe: el desplegable se puede reemplazar a mano.
            $libroId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $libro = $libroId === false ? null : $catalogo->porId($libroId);
            if ($libro === null) {
                $errores["libro-{$i}"] = 'Ese libro no está en el catálogo. Elegí uno de la lista.';
                continue;
            }
            if (isset($vistos[$libro['id']])) {
                $errores["libro-{$i}"] = "«{$libro['titulo']}» ya está en otro renglón: cambiá la cantidad de ese.";
                continue;
            }
            $vistos[$libro['id']] = true;

            // El stock lo devuelve el repositorio aunque la interfaz no lo exija: un catálogo
            // que no lo sabe no limita.
            $stock = isset($libro['stock']) ? (int) $libro['stock'] : null;
            if ($stock === 0) {
                $errores["libro-{$i}"] = "«{$libro['titulo']}» no tiene ejemplares en este momento.";
                continue;
            }

            $tope = min(self::MAX_EJEMPLARES, $stock ?? self::MAX_EJEMPLARES);
            $cantidad = filter_var(trim($renglon['cantidad']), FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => $tope],
            ]);
            if ($cantidad === false) {
                $errores["cantidad-{$i}"] = $tope < self::MAX_EJEMPLARES
                    ? ($tope === 1
                        ? "De «{$libro['titulo']}» queda un solo ejemplar: la cantidad tiene que ser 1."
                        : "De «{$libro['titulo']}» quedan {$tope}: la cantidad tiene que ser entre 1 y {$tope}.")
                    : 'La cantidad tiene que ser un número entero entre 1 y ' . self::MAX_EJEMPLARES . '.';
                continue;
            }

            $elegidos[] = ['libro' => $libro, 'cantidad' => $cantidad];
        }

        if ($elegidos === [] && $errores === []) {
            $errores['libro-0'] = 'Elegí el libro que querés reservar.';
        } elseif (count($vistos) > self::MAX_LIBROS) {
            $errores['libros'] = 'Una reserva puede tener hasta ' . self::MAX_LIBROS . ' libros distintos.';
        }

        return [$elegidos, $errores];
    }

    /** En pesos: la suma de cada libro por su cantidad. */
    public function total(): int
    {
        return array_sum(array_map(
            static fn (array $renglon): int => $renglon['libro']['precio'] * $renglon['cantidad'],
            $this->renglones,
        ));
    }

    /** Cuántos ejemplares son en total, sumando todos los libros. */
    public function ejemplares(): int
    {
        return array_sum(array_column($this->renglones, 'cantidad'));
    }

    /**
     * Los ids de los libros reservados, para el log y para sacarlos del carrito.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(static fn (array $renglon): int => $renglon['libro']['id'], $this->renglones);
    }

    /** En caracteres y no en bytes, como cuenta el maxlength del navegador: «ñ» es uno solo. */
    private static function largo(string $texto): int
    {
        return (int) preg_match_all('/./su', $texto);
    }
}
