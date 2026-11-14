<?php

declare(strict_types=1);

namespace PawPrints\Models;

/**
 * Un libro nuevo que pasó todas las validaciones del formulario de carga (TP4, consigna 2).
 *
 * La única forma de crearlo es desdeFormulario(), así que un LibroNuevo que existe es válido.
 * Lo guarda LibroRepository::agregar() en la tabla libros (TP5).
 */
final class LibroNuevo
{
    public const MAX_TITULO = 150;
    public const MAX_AUTOR = 100;
    public const MAX_RESUMEN = 300;
    public const MAX_DESCRIPCION = 2000;
    public const MAX_PRECIO = 1_000_000;
    public const MAX_STOCK = 9_999;

    /**
     * El ISBN-13: 978 o 979 y diez números más, con espacios o guiones opcionales entre ellos. El
     * mismo patrón va en el atributo pattern del HTML.
     */
    public const PATRON_ISBN = '(?:97[89])(?:[\s\-]?\d){10}';

    public const MAX_TAMANO_TAPA = 2 * 1024 * 1024; // 2 MB (upload_max_filesize por defecto de PHP)

    /** @var array<string, string> tipo MIME real => extensión */
    public const MIMES_TAPA = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Los mensajes de error de cada campo. Son los mismos en el servidor y en el navegador: la vista
     * los pasa a los atributos data-error-* de cada control, de donde los toma validador.js. Así el
     * usuario lee el mismo mensaje lo detecte uno u otro.
     *
     * @var array<string, array<string, string>> campo => tipo de error => mensaje
     */
    public const MENSAJES = [
        'titulo' => [
            'requerido' => 'Escribí el título del libro.',
            'largo' => 'El título puede tener hasta 150 caracteres.',
            'caracteres' => 'El título tiene caracteres que no se pueden usar.',
        ],
        'autor' => [
            'requerido' => 'Escribí el nombre del autor.',
            'largo' => 'El autor puede tener hasta 100 caracteres.',
            'caracteres' => 'El autor tiene caracteres que no se pueden usar.',
        ],
        'genero' => [
            'requerido' => 'Elegí el género.',
            'lista' => 'Elegí un género de la lista.',
        ],
        'formato' => [
            'requerido' => 'Elegí el formato.',
            'lista' => 'Elegí un formato de la lista.',
        ],
        'isbn' => [
            'requerido' => 'Escribí el ISBN.',
            'formato' => 'El ISBN tiene 13 números y empieza con 978 o 979, como 978-987-1234-56-1.',
            'isbn' => 'El último número del ISBN no coincide con los demás: revisá que esté bien copiado.',
            'repetido' => 'Ya hay un libro con ese ISBN en el catálogo.',
        ],
        'precio' => [
            'requerido' => 'Escribí el precio.',
            'formato' => 'Escribí el precio sólo con números, sin puntos ni centavos, como 16500.',
            'rango' => 'El precio tiene que estar entre $1 y $1.000.000.',
        ],
        'stock' => [
            'requerido' => 'Escribí cuántos ejemplares hay.',
            'formato' => 'El stock es un número entero, como 7.',
            'rango' => 'El stock tiene que estar entre 0 y 9.999.',
        ],
        'resumen' => [
            'requerido' => 'Escribí un resumen corto del libro.',
            'largo' => 'El resumen puede tener hasta 300 caracteres.',
            'caracteres' => 'El resumen tiene caracteres que no se pueden usar.',
        ],
        'descripcion' => [
            'largo' => 'La descripción puede tener hasta 2000 caracteres.',
            'caracteres' => 'La descripción tiene caracteres que no se pueden usar.',
        ],
        'tapa' => [
            'tipo' => 'La tapa debe ser una imagen en formato JPG, PNG o WebP.',
            'tamano' => 'La imagen no puede pesar más de 2 MB.',
            'subida' => 'No se pudo subir la imagen de la tapa. Probá de nuevo.',
        ],
    ];

    private function __construct(
        public readonly string $titulo,
        public readonly string $autor,
        public readonly string $genero,
        public readonly string $formato,
        public readonly string $isbn,
        public readonly int $precio,
        public readonly int $stock,
        public readonly string $resumen,
        public readonly string $descripcion,
        public readonly ?string $imagen = null,
    ) {
    }

    /**
     * Valida lo que llegó del formulario. El navegador ya valida con validador.js y zona-arrastre.js,
     * pero eso se saltea desactivando el JavaScript o mandando el POST a mano: acá se vuelve a
     * validar todo, con las mismas reglas.
     *
     * @param array<string, string> $campos titulo, autor, genero, formato, isbn, precio, stock,
     *                                      resumen y descripcion
     * @param array<string, mixed>|null $tapa el archivo de $_FILES['tapa'] si se envió
     * @param (callable(array<string, mixed>, string): string)|null $guardarTapa función para guardar la tapa y obtener su nombre
     *
     * @throws LibroNuevoInvalido con un mensaje por cada campo que no pasó
     */
    public static function desdeFormulario(
        array $campos,
        LibroRepository $catalogo,
        ?array $tapa = null,
        ?callable $guardarTapa = null,
    ): self
    {
        $errores = [];
        $error = static function (string $campo, string $tipo) use (&$errores): void {
            $errores[$campo] ??= self::MENSAJES[$campo][$tipo];
        };

        // UTF-8 mal formado no es texto: no se puede medir ni mostrar.
        foreach ($campos as $campo => $valor) {
            if (preg_match('//u', $valor) !== 1) {
                $errores[$campo] = 'Tiene caracteres que no se pueden leer.';
                $campos[$campo] = '';
            }
        }

        $titulo = self::linea($campos['titulo'] ?? '');
        self::texto($titulo, 'titulo', self::MAX_TITULO, true, $error);

        $autor = self::linea($campos['autor'] ?? '');
        self::texto($autor, 'autor', self::MAX_AUTOR, true, $error);

        // Listas cerradas, armadas desde el catálogo: el desplegable se puede reemplazar a mano.
        $genero = trim($campos['genero'] ?? '');
        if ($genero === '') {
            $error('genero', 'requerido');
        } elseif (!array_key_exists($genero, $catalogo->generos())) {
            $error('genero', 'lista');
        }

        $formato = trim($campos['formato'] ?? '');
        if ($formato === '') {
            $error('formato', 'requerido');
        } elseif (!array_key_exists($formato, $catalogo->formatos())) {
            $error('formato', 'lista');
        }

        // Se guarda con los guiones del catálogo (978-987-…) solo si los trae; para comparar y
        // calcular el dígito verificador se usan sólo los números.
        $isbn = trim($campos['isbn'] ?? '');
        $digitos = (string) preg_replace('/\D/', '', $isbn);
        if ($isbn === '') {
            $error('isbn', 'requerido');
        } elseif (preg_match('/^' . self::PATRON_ISBN . '$/', $isbn) !== 1) {
            $error('isbn', 'formato');
        } elseif (!self::isbnValido($digitos)) {
            $error('isbn', 'isbn');
        } elseif ($catalogo->porIsbn($digitos) !== null) {
            $error('isbn', 'repetido');
        }

        $precio = self::entero($campos['precio'] ?? '', 'precio', 1, self::MAX_PRECIO, $error);
        $stock = self::entero($campos['stock'] ?? '', 'stock', 0, self::MAX_STOCK, $error);

        $resumen = self::parrafos($campos['resumen'] ?? '');
        self::texto($resumen, 'resumen', self::MAX_RESUMEN, true, $error);

        $descripcion = self::parrafos($campos['descripcion'] ?? '');
        self::texto($descripcion, 'descripcion', self::MAX_DESCRIPCION, false, $error);

        $mimeTapa = self::validarTapa($tapa, $error);

        if ($errores !== []) {
            throw new LibroNuevoInvalido($errores);
        }

        $imagen = null;
        if ($mimeTapa !== null && $tapa !== null && $guardarTapa !== null) {
            $imagen = $guardarTapa($tapa, $mimeTapa);
        }

        return new self($titulo, $autor, $genero, $formato, $isbn, $precio, $stock, $resumen, $descripcion, $imagen);
    }

    /**
     * El mismo libro con una tapa, para el que se cargó sin una y la consiguió de Open Library
     * (TP5, consigna 7). El resto ya pasó la validación, y la tapa la validó OpenLibrary::tapa().
     */
    public function conImagen(string $imagen): self
    {
        return new self(
            $this->titulo, $this->autor, $this->genero, $this->formato, $this->isbn,
            $this->precio, $this->stock, $this->resumen, $this->descripcion, $imagen,
        );
    }

    /**
     * Valida el archivo de tapa subido ($_FILES['tapa']).
     * Controla UPLOAD_ERR_OK, el tamaño máximo y el tipo MIME real mediante finfo_file,
     * sin confiar en el tipo que manda el navegador ni en la extensión.
     *
     * @param array<string, mixed>|null $tapa
     * @param callable(string, string): void $error
     * @return string|null el tipo MIME real validado, o null si no se envió archivo o hubo error
     */
    public static function validarTapa(?array $tapa, callable $error): ?string
    {
        if ($tapa === null) {
            return null;
        }

        $codigoError = $tapa['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($codigoError === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($codigoError === UPLOAD_ERR_INI_SIZE || $codigoError === UPLOAD_ERR_FORM_SIZE) {
            $error('tapa', 'tamano');

            return null;
        }

        if ($codigoError !== UPLOAD_ERR_OK) {
            $error('tapa', 'subida');

            return null;
        }

        $tamano = (int) ($tapa['size'] ?? 0);
        if ($tamano > self::MAX_TAMANO_TAPA) {
            $error('tapa', 'tamano');

            return null;
        }

        $tmpName = (string) ($tapa['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            $error('tapa', 'subida');

            return null;
        }

        // Sin finfo_close: desde PHP 8.1 el objeto finfo se libera solo, y en 8.5 la función está
        // deprecada; el ErrorHandler convierte ese aviso en excepción y la subida daba un 500.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpName) : false;

        if (!is_string($mime) || !array_key_exists($mime, self::MIMES_TAPA)) {
            $error('tapa', 'tipo');

            return null;
        }

        return $mime;
    }

    /**
     * El dígito verificador del ISBN-13: los doce primeros números se multiplican, alternando, por
     * 1 y por 3; el decimotercero es lo que le falta a esa suma para llegar a un múltiplo de 10.
     * validador.js hace la misma cuenta en la página.
     */
    public static function isbnValido(string $digitos): bool
    {
        if (preg_match('/^\d{13}$/', $digitos) !== 1) {
            return false;
        }
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $digitos[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $suma % 10) % 10 === (int) $digitos[12];
    }

    /** Una sola línea: los espacios de más y cualquier salto de línea se juntan en uno. */
    private static function linea(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Texto de varias líneas. El navegador manda los saltos del textarea como CRLF, pero su
     * maxlength los cuenta como un carácter: se pasan a \n antes de medir.
     */
    private static function parrafos(string $texto): string
    {
        return trim(str_replace("\r\n", "\n", $texto));
    }

    /**
     * @param callable(string, string): void $error
     */
    private static function texto(string $texto, string $campo, int $maximo, bool $obligatorio, callable $error): void
    {
        if ($texto === '') {
            if ($obligatorio) {
                $error($campo, 'requerido');
            }
        } elseif (self::largo($texto) > $maximo) {
            $error($campo, 'largo');
        } elseif (preg_match('/[^\P{Cc}\n\t]/u', $texto) === 1) {
            $error($campo, 'caracteres');
        }
    }

    /**
     * Sólo números: ni signo, ni puntos de miles, ni decimales. FILTER_VALIDATE_INT aceptaría «+5».
     *
     * @param callable(string, string): void $error
     */
    private static function entero(string $valor, string $campo, int $minimo, int $maximo, callable $error): int
    {
        $valor = trim($valor);
        if ($valor === '') {
            $error($campo, 'requerido');

            return 0;
        }
        if (!ctype_digit($valor)) {
            $error($campo, 'formato');

            return 0;
        }
        // Se compara como texto antes de convertir: un número enorme no entra en un int.
        $numero = strlen(ltrim($valor, '0')) > 9 ? PHP_INT_MAX : (int) $valor;
        if ($numero < $minimo || $numero > $maximo) {
            $error($campo, 'rango');
        }

        return $numero;
    }

    /** En caracteres y no en bytes, como cuenta el maxlength del navegador: «ñ» es uno solo. */
    private static function largo(string $texto): int
    {
        return (int) preg_match_all('/./su', $texto);
    }
}
