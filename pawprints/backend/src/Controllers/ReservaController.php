<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Email;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Models\CatalogoDeLibros;
use PawPrints\Models\LibroRepository;
use PawPrints\Models\Reserva;
use PawPrints\Models\ReservaInvalida;
use PawPrints\Models\ReservaRepository;
use PawPrints\Models\Tienda;
use RuntimeException;

/**
 * La reserva de uno o más libros: el formulario, su envío (se guarda en la base, y salen un correo
 * al personal y otro de confirmación al cliente) y la página de confirmación.
 *
 * Desde el carrito (TP5-11) una reserva puede tener varios libros: el formulario manda un renglón
 * por libro, en los campos libro_id[] y cantidad[].
 * Responsabilidad: Mateo (Punto 5).
 */
class ReservaController extends Controller
{
    private const CAMPOS = ['nombre', 'email', 'telefono', 'comentarios'];

    /** Un campo oculto que una persona no ve y un bot completa: si llega con algo, es un bot. */
    private const TRAMPA = 'sitio_web';

    private ?CatalogoDeLibros $catalogo = null;

    private ?ReservaRepository $reservas = null;

    /**
     * GET /reserva. Con /reserva?libro=3 (el «Reservar en tienda» de la ficha) llega ese libro ya
     * elegido; con /reserva?libros[3]=2&libros[7]=1 (el «Reservar» del carrito), un renglón por
     * libro con su cantidad. Lo que no sea un id o una cantidad se descarta: la validación es del
     * POST, y acá sólo se completa el formulario.
     */
    public function formulario(Request $request): Response
    {
        $renglones = [];
        foreach ($request->queryArray('libros') as $id => $cantidad) {
            if (count($renglones) < Reserva::MAX_LIBROS && filter_var($id, FILTER_VALIDATE_INT) !== false) {
                $renglones[] = ['libro_id' => (string) $id, 'cantidad' => $cantidad];
            }
        }
        if ($renglones === [] && ($libro = $request->query('libro', '')) !== '') {
            $renglones[] = ['libro_id' => $libro, 'cantidad' => '1'];
        }

        return $this->mostrar([], $renglones);
    }

    /** POST /reserva */
    public function enviar(Request $request): Response
    {
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $campos[$campo] = $request->input($campo, '');
        }
        $renglones = self::renglones($request);

        // Al bot se le responde como si hubiera salido bien, para que no aprenda a esquivarla.
        if ($request->input(self::TRAMPA, '') !== '') {
            $this->logger->notice('Reserva descartada: se completó el campo trampa.');

            return Response::redirect('/reserva/confirmacion', 303);
        }

        try {
            $reserva = Reserva::desdeFormulario($campos, $renglones, $this->catalogo());
        } catch (ReservaInvalida $error) {
            return $this->mostrar($campos, $renglones, $error->errores, 422);
        }

        // Primero se guarda, y recién después salen los correos: si el guardado falla no se manda
        // nada, para que el personal no reciba el aviso de una reserva que no existe. El cliente
        // ve el mismo 503 de cuando no salía el aviso. (PDOException es un RuntimeException.)
        try {
            $numero = $this->reservas()->guardar($reserva);
        } catch (RuntimeException $error) {
            $this->logger->error('No se pudo guardar una reserva de los libros {libros}', [
                'libros' => implode(', ', $reserva->ids()),
                'exception' => $error,
            ]);

            return $this->mostrar($campos, $renglones, [
                'envio' => 'No pudimos registrar tu reserva. Probá de nuevo en unos minutos o llamanos al ' . Tienda::DATOS['telefono'] . '.',
            ], 503);
        }

        // Hasta el TP4, si el aviso al personal no salía la reserva se perdía, y por eso el cliente
        // veía un 503 y volvía al formulario. Desde el TP5 ya quedó guardada y el personal la ve en
        // su listado, marcada como «aviso sin enviar»: al cliente se le confirma igual y el error
        // queda en el log con el número, para buscarla.
        try {
            $this->mailer->send($this->avisoAlPersonal($reserva, $numero));
            $avisado = true;
        } catch (RuntimeException $error) {
            $avisado = false;
            $this->logger->error('No salió el aviso de la reserva {numero}: queda como aviso sin enviar', [
                'numero' => $numero,
                'exception' => $error,
            ]);
        }

        // Si falla la marca, el cliente no tiene por qué enterarse: en el listado figura como
        // aviso sin enviar, que es lo prudente, y el log dice que sí salió.
        if ($avisado) {
            try {
                $this->reservas()->avisoEnviado($numero);
            } catch (RuntimeException $error) {
                $this->logger->warning('Salió el aviso de la reserva {numero}, pero no se pudo marcar en la base', [
                    'numero' => $numero,
                    'exception' => $error,
                ]);
            }
        }

        // La reserva está guardada y el personal la va a ver: si la confirmación no sale, alcanza
        // con dejarlo en el log.
        try {
            $this->mailer->send($this->confirmacionAlCliente($reserva, $numero));
        } catch (RuntimeException $error) {
            $this->logger->warning('No salió la confirmación de la reserva {numero}', [
                'numero' => $numero,
                'exception' => $error,
            ]);
        }

        // Sin los datos del cliente: el log no guarda datos personales.
        $this->logger->info('Reserva {numero} guardada: libros {libros}, {cantidad} ejemplar(es), aviso {aviso}', [
            'numero' => $numero,
            'aviso' => $avisado ? 'enviado' : 'sin enviar',
            'libros' => implode(', ', $reserva->ids()),
            'cantidad' => $reserva->ejemplares(),
        ]);

        // 303 y a otra página: recargar la confirmación no vuelve a mandar la reserva. Los ids van
        // en la dirección para que el carrito saque los libros que ya se reservaron.
        return Response::redirect('/reserva/confirmacion?' . http_build_query(['libros' => implode(',', $reserva->ids())]), 303);
    }

    /**
     * GET /reserva/confirmacion?libros=3,7. Los ids sólo le dicen al carrito qué sacar: la página
     * no muestra nada de la reserva, así que una dirección armada a mano no revela nada.
     */
    public function confirmacion(Request $request): Response
    {
        $reservados = array_values(array_filter(
            array_map('intval', explode(',', (string) $request->query('libros', ''))),
            static fn (int $id): bool => $id > 0,
        ));

        return $this->render('reserva-confirmacion', [
            'reservados' => $reservados,
            'title' => 'Reserva recibida — PAWPrints',
            'description' => 'Recibimos tu reserva en PAWPrints.',
            'activePage' => 'catalogo',
            'pageClass' => 'page-reserva-confirmacion',
            'extraCss' => ['reserva.css'],
        ]);
    }

    /**
     * El repositorio de libros del punto 4, que implementa CatalogoDeLibros. Es protected para que
     * una prueba pueda darle otro catálogo.
     */
    protected function nuevoCatalogo(): CatalogoDeLibros
    {
        return new LibroRepository($this->db);
    }

    private function catalogo(): CatalogoDeLibros
    {
        return $this->catalogo ??= $this->nuevoCatalogo();
    }

    private function reservas(): ReservaRepository
    {
        return $this->reservas ??= new ReservaRepository($this->db);
    }

    /**
     * Los renglones del POST: libro_id[] y cantidad[] van en el mismo orden, uno por renglón.
     *
     * @return list<array{libro_id: string, cantidad: string}>
     */
    private static function renglones(Request $request): array
    {
        $cantidades = array_values($request->inputArray('cantidad'));
        $renglones = [];
        // Un tope por si llega un POST armado a mano con miles de renglones: con uno de más
        // alcanza para que la validación diga que se pasó.
        foreach (array_slice(array_values($request->inputArray('libro_id')), 0, Reserva::MAX_LIBROS + 1) as $i => $id) {
            $renglones[] = ['libro_id' => $id, 'cantidad' => $cantidades[$i] ?? ''];
        }

        return $renglones;
    }

    /**
     * El formulario, vacío o con lo que ya escribió el usuario y sus errores.
     *
     * @param array<string, string> $valores
     * @param list<array{libro_id: string, cantidad: string}> $renglones los libros elegidos; sin
     *        ninguno, el formulario muestra un renglón vacío
     * @param array<string, string> $errores
     */
    private function mostrar(array $valores, array $renglones = [], array $errores = [], int $estado = 200): Response
    {
        if ($renglones === []) {
            $renglones[] = ['libro_id' => '', 'cantidad' => '1'];
        }

        // Los de la tarjeta de resumen: los renglones con un libro que existe, con su subtotal.
        $elegidos = [];
        foreach ($renglones as $renglon) {
            $id = filter_var($renglon['libro_id'], FILTER_VALIDATE_INT);
            $libro = $id === false ? null : $this->catalogo()->porId($id);
            if ($libro !== null) {
                $cantidad = max(1, (int) filter_var($renglon['cantidad'], FILTER_VALIDATE_INT));
                $elegidos[] = ['libro' => $libro, 'cantidad' => $cantidad, 'subtotal' => $libro['precio'] * $cantidad];
            }
        }

        return $this->render('reserva', [
            'title' => 'Reservar un libro — PAWPrints',
            'description' => 'Formulario para reservar un libro del catálogo de PAWPrints y retirarlo en la tienda.',
            'activePage' => 'catalogo',
            'pageClass' => 'page-reserva',
            'extraCss' => ['reserva.css'],
            'libros' => $this->catalogo()->todos(),
            'renglones' => $renglones,
            'elegidos' => $elegidos,
            'total' => array_sum(array_column($elegidos, 'subtotal')),
            'valores' => $valores + array_fill_keys(self::CAMPOS, ''),
            'errores' => $errores,
            'maxEjemplares' => Reserva::MAX_EJEMPLARES,
            'maxLibros' => Reserva::MAX_LIBROS,
            'trampa' => self::TRAMPA,
        ], $estado);
    }

    /** @param int $numero el de la reserva guardada: con él se la encuentra en el listado */
    private function avisoAlPersonal(Reserva $reserva, int $numero): Email
    {
        return new Email(
            to: (string) $this->config->get('correo.reservas'),
            subject: "Nueva reserva #{$numero}: " . self::asunto($reserva),
            body: $this->view->render('correos/reserva-personal', ['reserva' => $reserva, 'numero' => $numero]),
            // Responder el aviso le escribe directo al cliente.
            replyTo: $reserva->email,
            replyToName: $reserva->nombre,
        );
    }

    /** @param int $numero el de la reserva, para que el cliente lo pueda citar si escribe */
    private function confirmacionAlCliente(Reserva $reserva, int $numero): Email
    {
        return new Email(
            to: $reserva->email,
            subject: "Tu reserva #{$numero} en PAWPrints: " . self::asunto($reserva),
            body: $this->view->render('correos/reserva-cliente', ['reserva' => $reserva, 'numero' => $numero]),
            toName: $reserva->nombre,
            // El remitente es una casilla que nadie lee: la respuesta del cliente va al personal.
            replyTo: (string) $this->config->get('correo.reservas'),
        );
    }

    /** «El Último Faro (2)» con un libro, o «El Último Faro y 2 libros más (5)» con varios. */
    private static function asunto(Reserva $reserva): string
    {
        $primero = $reserva->renglones[0]['libro']['titulo'];
        $otros = count($reserva->renglones) - 1;
        $mas = match ($otros) {
            0 => '',
            1 => ' y 1 libro más',
            default => " y {$otros} libros más",
        };

        return "{$primero}{$mas} ({$reserva->ejemplares()})";
    }
}
