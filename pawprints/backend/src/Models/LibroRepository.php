<?php

declare(strict_types=1);

namespace PawPrints\Models;

use PawPrints\Core\JsonRepository;

/**
 * El catálogo, guardado en data/libros.json. Resuelve la búsqueda, los filtros, el orden y el
 * paginado, y es la implementación de CatalogoDeLibros que usa la reserva del punto 5.
 * Responsabilidad: Agustina (Punto 4).
 *
 * Los métodos devuelven el libro completo, que es un superconjunto de las cuatro claves que
 * declara CatalogoDeLibros: la reserva usa las que necesita y el catálogo, el resto.
 *
 * @phpstan-type Libro array{id: int, titulo: string, autor: string, genero: string, isbn: string,
 *               formato: string, precio: int, stock: int, resumen: string, descripcion: string,
 *               imagen: string}
 */
final class LibroRepository extends JsonRepository implements CatalogoDeLibros
{
    protected const FILE = 'libros.json';

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

    /**
     * Los rangos de precio de la barra de filtros: valor de la query => [mínimo, máximo] en pesos.
     * Son los tres del wireframe; null es «sin límite».
     *
     * @var array<string, array{0: int, 1: int|null}>
     */
    public const RANGOS = [
        'hasta-15000' => [0, 14999],
        '15000-20000' => [15000, 20000],
        'desde-20000' => [20001, null],
    ];

    /**
     * El catálogo entero, en el orden del archivo.
     *
     * @return list<Libro>
     */
    public function todos(): array
    {
        /** @var list<Libro> */
        return $this->rows();
    }

    /**
     * @return Libro|null null si no existe: lo usan la ficha del libro y la validación de la reserva
     */
    public function porId(int $id): ?array
    {
        foreach ($this->todos() as $libro) {
            if ($libro['id'] === $id) {
                return $libro;
            }
        }

        return null;
    }

    /**
     * Los libros que coinciden con la búsqueda y los filtros, ya ordenados. El paginado se aplica
     * después, sobre este resultado, así el total de resultados se puede contar.
     *
     * Un filtro que no corresponde a nada del catálogo no es un error: deja la lista vacía, que es
     * lo que se le muestra a la persona. Un orden desconocido, en cambio, cae en el de por defecto.
     *
     * @param array{q?: string|null, genero?: string|null, formato?: string|null,
     *              precio?: string|null, orden?: string|null} $filtros tal como llegan de la query
     *
     * @return list<Libro>
     */
    public function buscar(array $filtros = []): array
    {
        $buscado = self::normalizar((string) ($filtros['q'] ?? ''));
        $genero = (string) ($filtros['genero'] ?? '');
        $formato = (string) ($filtros['formato'] ?? '');
        $rango = self::RANGOS[(string) ($filtros['precio'] ?? '')] ?? null;

        $libros = array_values(array_filter($this->todos(), static function (array $libro) use (
            $buscado,
            $genero,
            $formato,
            $rango,
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

            return $rango === null
                || ($libro['precio'] >= $rango[0] && ($rango[1] === null || $libro['precio'] <= $rango[1]));
        }));

        // El orden de por defecto es el del catálogo, que no necesita ordenar nada.
        match ((string) ($filtros['orden'] ?? '')) {
            'precio-asc' => usort($libros, static fn (array $a, array $b): int => $a['precio'] <=> $b['precio']),
            'precio-desc' => usort($libros, static fn (array $a, array $b): int => $b['precio'] <=> $a['precio']),
            'titulo-az' => usort(
                $libros,
                static fn (array $a, array $b): int => self::normalizar($a['titulo']) <=> self::normalizar($b['titulo']),
            ),
            default => null,
        };

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
     * @return array<string, int> valor => cuántos libros lo tienen, alfabético
     */
    private function contar(string $campo): array
    {
        $cuenta = [];
        foreach ($this->todos() as $libro) {
            $valor = (string) $libro[$campo];
            $cuenta[$valor] = ($cuenta[$valor] ?? 0) + 1;
        }
        ksort($cuenta);

        return $cuenta;
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
