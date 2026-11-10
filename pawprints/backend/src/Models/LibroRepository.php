<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\Database;
use PDO;
use PDOStatement;

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
     * Los libros que coinciden con la búsqueda y los filtros, ya ordenados. Con $limite, sólo esa
     * página: el filtrado, el orden y el corte los hace la consulta, y no se trae el catálogo
     * entero para filtrarlo en PHP. total() cuenta los mismos libros, para el paginado.
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
     * @param int|null $limite cuántos libros traer, o null para todos (el CSV)
     * @param int      $desde  cuántos saltear: (página - 1) × libros por página
     *
     * @return list<Libro>
     */
    public function buscar(array $filtros = [], ?int $limite = null, int $desde = 0): array
    {
        [$where, $parametros] = self::condiciones($filtros);
        $sql = 'SELECT ' . self::COLUMNAS . " FROM libros{$where} ORDER BY "
            . self::orden((string) ($filtros['orden'] ?? ''));

        if ($limite !== null) {
            // También como parámetros, y enteros: con las preparadas de verdad, MySQL no acepta
            // un texto en el LIMIT.
            $sql .= ' LIMIT :limite OFFSET :desde';
            $parametros['limite'] = max(0, $limite);
            $parametros['desde'] = max(0, $desde);
        }

        /** @var list<Libro> */
        return $this->ejecutar($sql, $parametros)->fetchAll();
    }

    /**
     * Cuántos libros da buscar() con estos filtros, sin paginar.
     *
     * @param array<string, string|null> $filtros los mismos de buscar()
     */
    public function total(array $filtros = []): int
    {
        [$where, $parametros] = self::condiciones($filtros);

        return (int) $this->ejecutar("SELECT COUNT(*) FROM libros{$where}", $parametros)->fetchColumn();
    }

    /**
     * El WHERE de la búsqueda y sus parámetros, que usan tanto la lista como el total. Lo que
     * escribió el usuario va siempre como parámetro y nunca pegado en el SQL (TP5, consigna 4).
     *
     * @param array<string, string|null> $filtros
     *
     * @return array{0: string, 1: array<string, string|int>}
     */
    private static function condiciones(array $filtros): array
    {
        $condiciones = [];
        $parametros = [];

        $buscado = trim((string) ($filtros['q'] ?? ''));
        if ($buscado !== '') {
            // Todo lo que la búsqueda mira de un libro, junto, como en el TP3: el ISBN va dos
            // veces, con y sin guiones, así se encuentra escrito de las dos formas. La
            // intercalación de la base no distingue tildes ni mayúsculas, así que «ficcion»
            // encuentra «Ficción» y «baez», «Báez».
            $condiciones[] = "CONCAT_WS(' ', titulo, autor, genero, isbn, REPLACE(isbn, '-', '')) LIKE :q";
            // El % y el _ que escribe el usuario buscan ese carácter, y no «cualquier cosa».
            $parametros['q'] = '%' . addcslashes($buscado, '\\%_') . '%';
        }

        // El género y el formato tienen que ser exactamente uno de los del catálogo, como en el
        // TP3: utf8mb4_bin distingue tildes y mayúsculas, que la intercalación de la base no.
        foreach (['genero', 'formato'] as $campo) {
            $valor = (string) ($filtros[$campo] ?? '');
            if ($valor !== '') {
                $condiciones[] = "{$campo} = :{$campo} COLLATE utf8mb4_bin";
                $parametros[$campo] = $valor;
            }
        }

        $minimo = self::monto($filtros['precio_desde'] ?? null);
        $maximo = self::monto($filtros['precio_hasta'] ?? null);
        if ($minimo === null || $maximo === null || $minimo <= $maximo) {
            if ($minimo !== null) {
                $condiciones[] = 'precio >= :minimo';
                $parametros['minimo'] = $minimo;
            }
            if ($maximo !== null) {
                $condiciones[] = 'precio <= :maximo';
                $parametros['maximo'] = $maximo;
            }
        }

        $where = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);

        return [$where, $parametros];
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
     * El ORDER BY de un orden de la query: título, autor o precio, en forma ascendente (`-asc`) o
     * descendente (`-desc`). Sale de esta lista cerrada porque una columna no se puede pasar como
     * parámetro: lo que escribe el usuario elige una opción y nunca llega al SQL. El autor va por
     * el nombre tal como está escrito, no por apellido, y los textos se comparan sin tildes ni
     * mayúsculas. El id desempata, así los empates quedan en el orden del catálogo. Un orden
     * desconocido, «recomendados» incluido, deja el orden del catálogo.
     */
    private static function orden(string $orden): string
    {
        $columna = match ($orden) {
            'titulo-asc' => 'titulo ASC, ',
            'titulo-desc' => 'titulo DESC, ',
            'autor-asc' => 'autor ASC, ',
            'autor-desc' => 'autor DESC, ',
            'precio-asc' => 'precio ASC, ',
            'precio-desc' => 'precio DESC, ',
            default => '',
        };

        return $columna . 'id';
    }

    /**
     * Los géneros del catálogo con cuántos libros tiene cada uno, para la barra de filtros. Se
     * cuentan sobre el catálogo entero y no sobre la búsqueda: son el mapa de lo que hay.
     *
     * @return array<string, int> género => cantidad
     */
    public function generos(): array
    {
        return $this->cuantosPor('genero');
    }

    /**
     * @return array<string, int> formato => cantidad
     */
    public function formatos(): array
    {
        return $this->cuantosPor('formato');
    }

    /**
     * Otros libros para ofrecer en la ficha de uno: primero los del mismo género y después el
     * resto, para que la lista llegue a $cantidad mientras haya catálogo. Dentro de cada grupo,
     * en el orden del catálogo.
     *
     * @param Libro $libro el que se está viendo, que queda afuera
     *
     * @return list<Libro>
     */
    public function recomendados(array $libro, int $cantidad = 3): array
    {
        /** @var list<Libro> */
        return $this->ejecutar(
            'SELECT ' . self::COLUMNAS . ' FROM libros WHERE id <> :id'
            . ' ORDER BY genero = :genero COLLATE utf8mb4_bin DESC, id LIMIT :cantidad',
            ['id' => $libro['id'], 'genero' => $libro['genero'], 'cantidad' => max(0, $cantidad)],
        )->fetchAll();
    }

    /**
     * @param 'genero'|'formato' $campo nunca lo que escribió el usuario: un nombre de columna no
     *                                  se puede pasar como parámetro de una consulta preparada
     *
     * @return array<string, int> valor => cuántos libros lo tienen, alfabético
     */
    private function cuantosPor(string $campo): array
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
        $libro = $this->ejecutar($sql, $parametros)->fetch();

        /** @var Libro|null */
        return $libro === false ? null : $libro;
    }

    /**
     * Prepara la consulta y le pasa los parámetros con su tipo: los enteros como enteros, que el
     * LIMIT no acepta como texto.
     *
     * @param array<int|string, mixed> $parametros por posición (lista) o por nombre
     */
    private function ejecutar(string $sql, array $parametros): PDOStatement
    {
        $consulta = $this->pdo()->prepare($sql);
        foreach ($parametros as $clave => $valor) {
            $consulta->bindValue(
                is_int($clave) ? $clave + 1 : $clave,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR,
            );
        }
        $consulta->execute();

        return $consulta;
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }
}
