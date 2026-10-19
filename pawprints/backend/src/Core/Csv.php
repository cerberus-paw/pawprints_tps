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
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
