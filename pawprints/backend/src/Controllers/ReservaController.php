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
 * La reserva de un libro: el formulario, su envío (se guarda en la base, y salen un correo al
 * personal y otro de confirmación al cliente) y la página de confirmación.
 * Responsabilidad: Mateo (Punto 5).
 */
class ReservaController extends Controller
{
    private const CAMPOS = ['nombre', 'email', 'telefono', 'libro_id', 'cantidad', 'comentarios'];

    /** Un campo oculto que una persona no ve y un bot completa: si llega con algo, es un bot. */
    private const TRAMPA = 'sitio_web';

    private ?CatalogoDeLibros $catalogo = null;

    private ?ReservaRepository $reservas = null;

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

        // Primero se guarda, y recién después salen los correos: si el guardado falla no se manda
        // nada, para que el personal no reciba el aviso de una reserva que no existe. El cliente
        // ve el mismo 503 de cuando no salía el aviso. (PDOException es un RuntimeException.)
        try {
            $numero = $this->reservas()->guardar($reserva);
        } catch (RuntimeException $error) {
            $this->logger->error('No se pudo guardar una reserva del libro {libro}', [
                'libro' => $reserva->libro['id'],
                'exception' => $error,
            ]);

            return $this->mostrar($campos, [
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
            $this->logger->error('No salió el aviso de la reserva {numero} del libro {libro}: queda como aviso sin enviar', [
                'numero' => $numero,
                'libro' => $reserva->libro['id'],
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
            $this->logger->warning('No salió la confirmación de la reserva {numero} del libro {libro}', [
                'numero' => $numero,
                'libro' => $reserva->libro['id'],
                'exception' => $error,
            ]);
        }

        // Sin los datos del cliente: el log no guarda datos personales.
        $this->logger->info('Reserva {numero} guardada: libro {libro}, {cantidad} ejemplar(es), aviso {aviso}', [
            'numero' => $numero,
            'aviso' => $avisado ? 'enviado' : 'sin enviar',
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

    /** @param int $numero el de la reserva guardada: con él se la encuentra en el listado */
    private function avisoAlPersonal(Reserva $reserva, int $numero): Email
    {
        return new Email(
            to: (string) $this->config->get('correo.reservas'),
            subject: "Nueva reserva #{$numero}: {$reserva->libro['titulo']} ({$reserva->cantidad})",
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
            subject: "Tu reserva #{$numero} en PAWPrints: {$reserva->libro['titulo']}",
            body: $this->view->render('correos/reserva-cliente', ['reserva' => $reserva, 'numero' => $numero]),
            toName: $reserva->nombre,
            // El remitente es una casilla que nadie lee: la respuesta del cliente va al personal.
            replyTo: (string) $this->config->get('correo.reservas'),
        );
    }
}
