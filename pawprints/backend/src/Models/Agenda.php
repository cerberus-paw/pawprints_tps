<?php

declare(strict_types=1);

namespace PawPrints\Models;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Las actividades fijas de la librería que anuncia la portada, con la fecha del próximo encuentro.
 *
 * Hasta el TP5 la portada tenía las fechas escritas a mano («Jueves 18 de septiembre») y quedaron
 * vencidas, y además con el día de la semana mal. Como las actividades se repiten todos los meses
 * con la misma regla, la fecha se calcula: la portada nunca anuncia algo que ya pasó.
 */
final class Agenda
{
    /**
     * La regla de cada actividad, en el formato relativo de DateTimeImmutable::modify(). «%s» es
     * el mes («first day of next month»…), que se completa al calcular.
     */
    private const ACTIVIDADES = [
        [
            'titulo' => 'Club de lectura: literatura latinoamericana',
            'texto' => 'Encuentro mensual abierto a la comunidad, moderado por un escritor invitado. El último jueves de cada mes.',
            'regla' => 'last thursday of %s',
            'hora' => [19, 0],
        ],
        [
            'titulo' => 'Taller de escritura creativa',
            'texto' => 'Clase abierta de redacción creativa para todas las edades, el primer sábado de cada mes. Cupos limitados.',
            'regla' => 'first saturday of %s',
            'hora' => [17, 30],
        ],
    ];

    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    private const MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /**
     * Las actividades ordenadas por fecha, cada una con su próximo encuentro: el de este mes si
     * todavía no pasó y si no, el del mes que viene.
     *
     * @return list<array{titulo: string, texto: string, fecha: DateTimeImmutable, cuando: string}>
     */
    public static function proximas(?DateTimeImmutable $ahora = null): array
    {
        $ahora ??= new DateTimeImmutable();
        $eventos = [];
        foreach (self::ACTIVIDADES as $actividad) {
            $fecha = self::encuentro($actividad, $ahora, 'this month');
            if ($fecha <= $ahora) {
                $fecha = self::encuentro($actividad, $ahora, 'next month');
            }
            $eventos[] = [
                'titulo' => $actividad['titulo'],
                'texto' => $actividad['texto'],
                'fecha' => $fecha,
                'cuando' => self::cuando($fecha),
            ];
        }
        usort($eventos, static fn (array $a, array $b): int => $a['fecha'] <=> $b['fecha']);

        return $eventos;
    }

    /** @param array{regla: string, hora: array{int, int}} $actividad */
    private static function encuentro(array $actividad, DateTimeImmutable $ahora, string $mes): DateTimeImmutable
    {
        // «first day of» antes de cambiar de mes: un 31 más «next month» saltearía febrero.
        return $ahora->modify('first day of ' . $mes)
            ->modify(sprintf($actividad['regla'], 'this month'))
            ->setTime(...$actividad['hora']);
    }

    /** «Jueves 26 de noviembre, 19:00 h», sin depender de la extensión intl ni del locale. */
    private static function cuando(DateTimeInterface $fecha): string
    {
        return self::DIAS[(int) $fecha->format('w')] . ' ' . $fecha->format('j') . ' de '
            . self::MESES[(int) $fecha->format('n')] . ', ' . $fecha->format('H:i') . ' h';
    }
}
