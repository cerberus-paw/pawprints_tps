<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;
use PDO;

/**
 * El catálogo, guardado en la tabla libros de la base (TP5; hasta el TP4, en data/libros.json).
 * Resuelve la búsqueda, los filtros, el orden y el paginado, y es la implementación de
 * CatalogoDeLibros que usa la reserva del punto 5. Los métodos públicos y lo que devuelven son los
 * mismos que con el JSON: el cambio de persistencia no toca ningún controlador ni vista.
 * Responsabilidad: Agustina (Punto 4).
 *
 * Los métodos devuelven el libro completo, que es un superconjunto de las cuatro claves que
 * declara CatalogoDeLibros: la reserva usa las que necesita y el catálogo, el resto.
 *
 * @phpstan-type Libro array{id: int, titulo: string, autor: string, genero: string, isbn: string,
 *               formato: string, precio: int, stock: int, resumen: string, descripcion: string,
 *               imagen: string|null}
 */
final class LibroRepository implements CatalogoDeLibros
{
    /**
     * Las columnas que forman un libro, las mismas claves que tenía cada objeto del JSON. Se
     * nombran una por una y no con «*»: isbn_digitos y creado son de la base y no del libro.
     */
    private const COLUMNAS = 'id, titulo, autor, genero, isbn, formato, precio, stock, resumen, descripcion, imagen';

    /**
     * Cuántos libros por página se pueden pedir con `?por_pagina=…`. Es una lista cerrada y no un
     * número libre: así un pedido no puede traerse el catálogo entero de una vez, y el desplegable
     * de la vista ofrece estas mismas opciones y no otras.
     *
     * @var list<int>
     */
    public const CANTIDADES = [4, 8, 12, 24];

    /** Cuántos libros entran en una página del catálogo si no se pide otra cantidad. */
    public const POR_PAGINA = self::CANTIDADES[0];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * El catálogo entero, en el orden en que se cargó: el del id, que es el que tenía el archivo.
     * Los enteros (id, precio, stock) llegan como int porque las consultas son preparadas de verdad
     * (ver Database).
     *
     * @return list<Libro>
     */
    public function todos(): array
    {
        /** @var list<Libro> */
        return $this->pdo()->query('SELECT ' . self::COLUMNAS . ' FROM libros ORDER BY id')->fetchAll();
    }

    /**
     * @return Libro|null null si no existe: lo usan la ficha del libro y la validación de la reserva
     */
    public function porId(int $id): ?array
    {
        return $this->uno('SELECT ' . self::COLUMNAS . ' FROM libros WHERE id = ?', [$id]);
    }

    /**
     * El libro con ese ISBN, comparando sólo los números: «978-987-1234-56-1» y «9789871234561»
     * son el mismo. Lo usa la carga de libros nuevos para no repetir uno que ya está. Compara
     * contra isbn_digitos, la columna que la base calcula y sobre la que está el índice único.
     *
     * @return Libro|null
     */
    public function porIsbn(string $isbn): ?array
    {
        return $this->uno(
            'SELECT ' . self::COLUMNAS . ' FROM libros WHERE isbn_digitos = ?',
            [preg_replace('/\D/', '', $isbn)],
        );
    }

    /**
     * Los libros que coinciden con la búsqueda y los filtros, ya ordenados. El paginado se aplica
     * después, sobre este resultado, así el total de resultados se puede contar.
     *
     * Un filtro que no corresponde a nada del catálogo no es un error: deja la lista vacía, que es
     * lo que se le muestra a la persona. Un orden desconocido, en cambio, cae en el de por defecto.
     *
     * El precio se acota con `precio_desde` y `precio_hasta`, en pesos e inclusivos: un monto
     * vacío o que no es un número entero es «sin límite», y si el mínimo supera al máximo el
     * filtro de precio no se aplica, porque no hay forma de saber cuál de los dos quiso decir.
     *
     * @param array{q?: string|null, genero?: string|null, formato?: string|null,
     *              precio_desde?: string|null, precio_hasta?: string|null,
     *              orden?: string|null} $filtros tal como llegan de la query
     *
     * @return list<Libro>
     */
    public function buscar(array $filtros = []): array
    {
        $buscado = self::normalizar((string) ($filtros['q'] ?? ''));
        $genero = (string) ($filtros['genero'] ?? '');
        $formato = (string) ($filtros['formato'] ?? '');
        $minimo = self::monto($filtros['precio_desde'] ?? null);
        $maximo = self::monto($filtros['precio_hasta'] ?? null);
        if ($minimo !== null && $maximo !== null && $minimo > $maximo) {
            $minimo = $maximo = null;
        }

        $libros = array_values(array_filter($this->todos(), static function (array $libro) use (
            $buscado,
            $genero,
            $formato,
            $minimo,
            $maximo,
        ): bool {
            if ($buscado !== '' && !str_contains(self::indice($libro), $buscado)) {
                return false;
            }
            if ($genero !== '' && $libro['genero'] !== $genero) {
                return false;
            }
            if ($formato !== '' && $libro['formato'] !== $formato) {
                return false;
            }

            return ($minimo === null || $libro['precio'] >= $minimo)
                && ($maximo === null || $libro['precio'] <= $maximo);
        }));

        return self::ordenar($libros, (string) ($filtros['orden'] ?? ''));
    }

    /** Un monto en pesos de la query, o null si está vacío o no es un entero no negativo. */
    private static function monto(?string $valor): ?int
    {
        $monto = filter_var(
            trim((string) $valor),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );

        return $monto === false ? null : $monto;
    }

    /**
     * Ordena por título, autor o precio, en forma ascendente (`-asc`) o descendente (`-desc`). El
     * autor va por el nombre tal como está escrito, no por apellido, y los textos se comparan sin
     * tildes ni mayúsculas. usort es estable desde PHP 8.0: los empates quedan en el orden del
     * catálogo. Un orden desconocido, «recomendados» incluido, deja el orden del catálogo.
     *
     * @param list<Libro> $libros
     *
     * @return list<Libro>
     */
    private static function ordenar(array $libros, string $orden): array
    {
        $partes = explode('-', $orden);
        if (count($partes) !== 2 || !in_array($partes[1], ['asc', 'desc'], true)) {
            return $libros;
        }
        [$campo, $sentido] = $partes;

        $clave = match ($campo) {
            'titulo', 'autor' => static fn (array $libro): string =>
                self::normalizar($libro[$campo]),
            'precio' => static fn (array $libro): int => $libro['precio'],
            default => null,
        };
        if ($clave === null) {
            return $libros;
        }

        $signo = $sentido === 'asc' ? 1 : -1;
        usort($libros, static fn (array $a, array $b): int => $signo * ($clave($a) <=> $clave($b)));

        return $libros;
    }

    /**
     * Los géneros del catálogo con cuántos libros tiene cada uno, para la barra de filtros. Se
     * cuentan sobre el catálogo entero y no sobre la búsqueda: son el mapa de lo que hay.
     *
     * @return array<string, int> género => cantidad
     */
    public function generos(): array
    {
        return $this->contar('genero');
    }

    /**
     * @return array<string, int> formato => cantidad
     */
    public function formatos(): array
    {
        return $this->contar('formato');
    }

    /**
     * Otros libros para ofrecer en la ficha de uno: primero los del mismo género y después el
     * resto, para que la lista llegue a $cantidad mientras haya catálogo.
     *
     * @param Libro $libro el que se está viendo, que queda afuera
     *
     * @return list<Libro>
     */
    public function recomendados(array $libro, int $cantidad = 3): array
    {
        $otros = array_values(array_filter(
            $this->todos(),
            static fn (array $otro): bool => $otro['id'] !== $libro['id'],
        ));

        // usort es estable desde PHP 8.0: los del mismo género suben y el resto queda en el orden
        // del catálogo.
        usort(
            $otros,
            static fn (array $a, array $b): int => (int) ($b['genero'] === $libro['genero'])
                <=> (int) ($a['genero'] === $libro['genero']),
        );

        return array_slice($otros, 0, $cantidad);
    }

    /**
     * @param 'genero'|'formato' $campo nunca lo que escribió el usuario: un nombre de columna no
     *                                  se puede pasar como parámetro de una consulta preparada
     *
     * @return array<string, int> valor => cuántos libros lo tienen, alfabético
     */
    private function contar(string $campo): array
    {
        $filas = $this->pdo()
            ->query("SELECT {$campo}, COUNT(*) FROM libros GROUP BY {$campo} ORDER BY {$campo}")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return array_map('intval', $filas);
    }

    /**
     * El primer libro de una consulta, o null si no trajo ninguno.
     *
     * @param list<mixed> $parametros
     *
     * @return Libro|null
     */
    private function uno(string $sql, array $parametros): ?array
    {
        $consulta = $this->pdo()->prepare($sql);
        $consulta->execute($parametros);
        $libro = $consulta->fetch();

        /** @var Libro|null */
        return $libro === false ? null : $libro;
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }

    /**
     * Todo lo que la búsqueda mira de un libro, junto y normalizado. El ISBN va dos veces, con y
     * sin guiones, así se encuentra escrito de las dos formas.
     *
     * @param Libro $libro
     */
    private static function indice(array $libro): string
    {
        return self::normalizar(implode(' ', [
            $libro['titulo'],
            $libro['autor'],
            $libro['genero'],
            $libro['isbn'],
            str_replace('-', '', $libro['isbn']),
        ]));
    }

    /**
     * Sin tildes y en minúsculas, para que «ficcion» encuentre «Ficción» y «baez», «Baez». Con
     * strtr y no con mb_* ni iconv: el TP3 no depende de extensiones que el hosting del punto 7
     * puede no tener instaladas.
     */
    private static function normalizar(string $texto): string
    {
        $sinTildes = strtr(trim($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);

        return strtolower($sinTildes);
    }
}
