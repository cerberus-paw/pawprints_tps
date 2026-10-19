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
use RuntimeException;

/**
 * La reserva de un libro: el formulario, su envío (un correo al personal y otro de confirmación
 * al cliente) y la página de confirmación.
 * Responsabilidad: Mateo (Punto 5).
 */
class ReservaController extends Controller
{
    private const CAMPOS = ['nombre', 'email', 'telefono', 'libro_id', 'cantidad', 'comentarios'];

    /** Un campo oculto que una persona no ve y un bot completa: si llega con algo, es un bot. */
    private const TRAMPA = 'sitio_web';

    private ?CatalogoDeLibros $catalogo = null;

    /** GET /reserva, o /reserva?libro=3 desde el detalle de un libro, que llega ya elegido. */
    public function formulario(Request $request): Response
    {
        return $this->mostrar(['libro_id' => $request->query('libro', '')]);
    }

    /** POST /reserva */
    public function enviar(Request $request): Response
    {
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $campos[$campo] = $request->input($campo, '');
        }

        // Al bot se le responde como si hubiera salido bien, para que no aprenda a esquivarla.
        if ($request->input(self::TRAMPA, '') !== '') {
            $this->logger->notice('Reserva descartada: se completó el campo trampa.');

            return Response::redirect('/reserva/confirmacion', 303);
        }

        try {
            $reserva = Reserva::desdeFormulario($campos, $this->catalogo());
        } catch (ReservaInvalida $error) {
            return $this->mostrar($campos, $error->errores, 422);
        }

        // La reserva no se guarda en ningún lado: si el aviso al personal no sale, se perdió, y
        // el cliente tiene que saberlo. Vuelve a ver el formulario con lo que había escrito.
        try {
            $this->mailer->send($this->avisoAlPersonal($reserva));
        } catch (RuntimeException $error) {
            $this->logger->error('No salió el aviso de una reserva del libro {libro}', [
                'libro' => $reserva->libro['id'],
                'exception' => $error,
            ]);

            return $this->mostrar($campos, [
                'envio' => 'No pudimos registrar tu reserva. Probá de nuevo en unos minutos o llamanos al +54 190168.',
            ], 503);
        }

        // El personal ya la tiene y va a contactar al cliente: si la confirmación no sale, alcanza
        // con dejarlo en el log.
        try {
            $this->mailer->send($this->confirmacionAlCliente($reserva));
        } catch (RuntimeException $error) {
            $this->logger->warning('No salió la confirmación de una reserva del libro {libro}', [
                'libro' => $reserva->libro['id'],
                'exception' => $error,
            ]);
        }

        // Sin los datos del cliente: el log no guarda datos personales.
        $this->logger->info('Reserva enviada: libro {libro}, {cantidad} ejemplar(es)', [
            'libro' => $reserva->libro['id'],
            'cantidad' => $reserva->cantidad,
        ]);

        // 303 y a otra página: recargar la confirmación no vuelve a mandar la reserva.
        return Response::redirect('/reserva/confirmacion', 303);
    }

    /** GET /reserva/confirmacion */
    public function confirmacion(Request $request): Response
    {
        return $this->render('reserva-confirmacion', [
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
        return new LibroRepository();
    }

    private function catalogo(): CatalogoDeLibros
    {
        return $this->catalogo ??= $this->nuevoCatalogo();
    }

    /**
     * El formulario, vacío o con lo que ya escribió el usuario y sus errores.
     *
     * @param array<string, string> $valores
     * @param array<string, string> $errores
     */
    private function mostrar(array $valores, array $errores = [], int $estado = 200): Response
    {
        $libroId = filter_var($valores['libro_id'] ?? '', FILTER_VALIDATE_INT);

        return $this->render('reserva', [
            'title' => 'Reservar un libro — PAWPrints',
            'description' => 'Formulario para reservar un libro del catálogo de PAWPrints y retirarlo en la tienda.',
            'activePage' => 'catalogo',
            'pageClass' => 'page-reserva',
            'extraCss' => ['reserva.css'],
            'libros' => $this->catalogo()->todos(),
            // El libro de la tarjeta de resumen: el que vino elegido, si existe.
            'libro' => $libroId === false ? null : $this->catalogo()->porId($libroId),
            'valores' => $valores + ['cantidad' => '1'] + array_fill_keys(self::CAMPOS, ''),
            'errores' => $errores,
            'maxEjemplares' => Reserva::MAX_EJEMPLARES,
            'trampa' => self::TRAMPA,
        ], $estado);
    }

    private function avisoAlPersonal(Reserva $reserva): Email
    {
        return new Email(
            to: (string) $this->config->get('correo.reservas'),
            subject: "Nueva reserva: {$reserva->libro['titulo']} ({$reserva->cantidad})",
            body: $this->view->render('correos/reserva-personal', ['reserva' => $reserva]),
            // Responder el aviso le escribe directo al cliente.
            replyTo: $reserva->email,
            replyToName: $reserva->nombre,
        );
    }

    private function confirmacionAlCliente(Reserva $reserva): Email
    {
        return new Email(
            to: $reserva->email,
            subject: "Tu reserva en PAWPrints: {$reserva->libro['titulo']}",
            body: $this->view->render('correos/reserva-cliente', ['reserva' => $reserva]),
            toName: $reserva->nombre,
            // El remitente es una casilla que nadie lee: la respuesta del cliente va al personal.
            replyTo: (string) $this->config->get('correo.reservas'),
        );
    }
}
