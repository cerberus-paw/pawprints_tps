<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * La representación en CSV de una tabla de datos. Es al CSV lo que View es al HTML: el
 * controlador elige la representación y la envuelve en la Response que corresponde.
 */
final class Csv
{
    /**
     * @param list<string>              $header
     * @param iterable<list<scalar|null>> $rows
     */
    public static function generate(array $header, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        // Sin la marca de orden de bytes, Excel lee el UTF-8 como Latin-1 y rompe las tildes.
        fwrite($stream, "\u{FEFF}");
        // Sin carácter de escape: las comillas se duplican, como pide el RFC 4180.
        fputcsv($stream, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(self::sinFormula(...), $row), ',', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * Desde el TP5 los libros se cargan desde el sitio, y un título como «=HIPERVINCULO(…)» se
     * abriría en la planilla como una fórmula. Un texto que empieza con =, +, -, @, tabulación o
     * retorno de carro lleva adelante un apóstrofo, que la planilla toma como «esto es texto» y
     * no muestra. Los números quedan como están: un precio no es una fórmula.
     */
    private static function sinFormula(mixed $celda): mixed
    {
        if (is_string($celda) && $celda !== '' && strpbrk($celda[0], "=+-@\t\r") !== false) {
            return "'" . $celda;
        }

        return $celda;
    }
}
