<?php

declare(strict_types=1);

namespace PawPrints;

use LogicException;
use PawPrints\Core\Config;
use PawPrints\Core\Controller;
use PawPrints\Core\ErrorHandler;
use PawPrints\Core\FileMailer;
use PawPrints\Core\HttpException;
use PawPrints\Core\Logger;
use PawPrints\Core\Mailer;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Core\Router;
use PawPrints\Core\SmtpMailer;
use PawPrints\Core\View;
use RuntimeException;
use Throwable;

/**
 * El recorrido de cada pedido: Request → Router → Controller (Models y View) → Response. Arma
 * las piezas del núcleo una vez y se las pasa a cada controlador, que no crea ninguna por su cuenta.
 */
final class App
{
    /**
     * Qué dice la página de error en cada estado: el título y el mensaje de respaldo, para cuando
     * el error no trae uno escrito (una HttpException sí lo trae: «No encontramos ese libro»).
     * El 400 hace de respaldo de cualquier otro 4xx, y el 500, de cualquier otro 5xx.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const MENSAJES = [
        400 => ['No pudimos con ese pedido', 'Revisá la dirección: puede estar incompleta o mal escrita.'],
        404 => ['Página no encontrada', 'La dirección que pediste no existe, o la página se movió de lugar.'],
        405 => ['Ese pedido no se puede hacer acá', 'La página existe, pero no se llega a ella de esta forma.'],
        500 => ['Algo se rompió de nuestro lado', 'No pudimos completar lo que pediste. El error ya quedó registrado y lo vamos a revisar.'],
    ];

    public function __construct(
        private readonly Router $router,
        private readonly View $view,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly Mailer $mailer,
    ) {
    }

    /**
     * Si la aplicación ni siquiera arranca (por ejemplo, porque falta config.ini) todavía no hay
     * logger ni vistas: el motivo va al log de PHP, que con php -S es la terminal, y al cliente
     * sólo el 500 genérico.
     */
    public static function run(string $backendDir): void
    {
        // Antes que nada: lo que falle de acá para abajo —incluido el propio arranque— tiene que
        // terminar en el log y no en la pantalla del cliente (punto 6).
        $errores = ErrorHandler::register();

        try {
            $app = self::build($backendDir);
        } catch (Throwable $error) {
            $errores->excepcionSuelta($error, 'PAWPrints no arrancó');

            return;
        }

        // Desde acá, los avisos de PHP, las excepciones sueltas y los fatales quedan en el log de
        // la aplicación, junto con el resto de los pedidos.
        $errores->conLogger($app->logger);

        $app->handle(Request::fromGlobals())->send();
    }

    public static function build(string $backendDir): self
    {
        $config = Config::fromFile($backendDir . '/config/config.ini');
        date_default_timezone_set((string) $config->get('app.zona_horaria', 'UTC'));
        $logger = new Logger(
            $backendDir . '/' . $config->get('log.archivo', 'logs/app.log'),
            (string) $config->get('log.nivel', 'info'),
        );

        $router = new Router();
        $declareRoutes = require $backendDir . '/routes.php';
        $declareRoutes($router);

        return new self(
            $router,
            new View($backendDir . '/views'),
            $config,
            $logger,
            self::mailer($config, $backendDir),
        );
    }

    /**
     * El transporte de los correos sale de correo.transporte: «archivo» en desarrollo, para no
     * mandar nada de verdad, y «smtp» en el servidor publicado.
     */
    private static function mailer(Config $config, string $backendDir): Mailer
    {
        $from = (string) $config->get('correo.remitente', '');
        if ($from === '') {
            throw new RuntimeException('Falta correo.remitente en config.ini.');
        }
        $fromName = (string) $config->get('correo.nombre', 'PAWPrints');

        return match ($transport = $config->get('correo.transporte', 'archivo')) {
            'archivo' => new FileMailer(
                $backendDir . '/' . $config->get('correo.carpeta', 'logs/correos'),
                $from,
                $fromName,
            ),
            'smtp' => new SmtpMailer(
                (string) $config->get('smtp.servidor', ''),
                (int) $config->get('smtp.puerto', 587),
                (string) $config->get('smtp.seguridad', 'tls'),
                (string) $config->get('smtp.usuario', ''),
                (string) $config->get('smtp.clave', ''),
                $from,
                $fromName,
            ),
            default => throw new RuntimeException("correo.transporte tiene que ser «archivo» o «smtp», no «{$transport}»."),
        };
    }

    public function handle(Request $request): Response
    {
        $start = hrtime(true);
        // Lo que un controlador imprima por su cuenta (un echo o un var_dump que quedaron de una
        // prueba) no puede salir antes de la respuesta: si después falla, se descarta junto con el
        // error, y si no, se decide abajo qué hacer con eso según el entorno.
        ob_start();
        try {
            [$action, $params] = $this->router->resolve($request->method(), $request->path());
            $response = $this->dispatch($action, $request->withRouteParams($params));
            $suelto = (string) ob_get_clean();
            if ($suelto !== '') {
                $this->logger->warning('{path} imprimió por fuera de la Response', ['path' => $request->path()]);
                // En desarrollo se muestra, para que el echo olvidado se vea y se saque. En
                // producción se descarta: nadie revisó qué tiene adentro y puede ser cualquier
                // cosa, desde una ruta del servidor hasta los datos de una reserva.
                if (!$this->config->inProduction()) {
                    $response = $response->withBody($suelto . $response->body());
                }
            }
        } catch (Throwable $error) {
            ob_end_clean();
            $response = $this->errorResponse($error, $request);
        }

        // Una línea por pedido, sin la query ni el cuerpo: alcanza para ver qué falla o qué anda
        // lento, y no guarda lo que escribió el usuario (en la reserva van sus datos personales).
        $this->logger->info('{method} {path} {status} {ms} ms', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->status(),
            'ms' => intdiv(hrtime(true) - $start, 1_000_000),
        ]);

        return $response;
    }

    /**
     * @param array{0: class-string<Controller>, 1: string} $action
     */
    private function dispatch(array $action, Request $request): Response
    {
        [$class, $method] = $action;
        $response = (new $class($this->view, $this->config, $this->logger, $this->mailer))->$method($request);
        if (!$response instanceof Response) {
            throw new LogicException("{$class}::{$method} tiene que devolver una Response.");
        }

        return $response;
    }

    /**
     * El único lugar al que llegan los errores del pedido: decide el estado, registra lo que haya
     * que investigar después y devuelve la página de error. Lo que el cliente ve es siempre un
     * mensaje escrito para él; las rutas, el código y la traza quedan del lado del servidor.
     * Responsabilidad: Agustina (Punto 6).
     */
    private function errorResponse(Throwable $error, Request $request): Response
    {
        $esHttp = $error instanceof HttpException;
        $status = $esHttp ? $error->status : 500;

        // Un 4xx es parte del uso normal del sitio —un enlace viejo, una dirección escrita a
        // mano— y ya quedó en la línea que registra cada pedido. Un 5xx es un problema nuestro:
        // va aparte, con la traza y con una referencia corta que también se le muestra a la
        // persona, para poder encontrar en el log el error del que vino a hablar.
        $referencia = null;
        if ($status >= 500) {
            $referencia = bin2hex(random_bytes(3));
            $this->logger->error('Error no controlado en {method} {path} (referencia {referencia})', [
                'method' => $request->method(),
                'path' => $request->path(),
                'referencia' => $referencia,
                'exception' => $error,
            ]);
        }

        // El detalle técnico sólo en desarrollo, y sólo de un error nuestro: el mensaje de una
        // HttpException ya se muestra completo y su traza no dice nada que sirva.
        $detalle = null;
        if (!$esHttp && !$this->config->inProduction()) {
            $detalle = [
                'clase' => $error::class,
                'mensaje' => $error->getMessage(),
                'origen' => $error->getFile() . ':' . $error->getLine(),
                'traza' => $error->getTraceAsString(),
            ];
        }

        $html = $this->errorHtml($status, $esHttp ? $error->getMessage() : null, $referencia, $detalle);

        // Las cabeceras de la excepción se conservan: un 405 tiene que llevar su Allow.
        $headers = ['Content-Type' => 'text/html; charset=utf-8'] + ($esHttp ? $error->headers : []);

        return new Response($html, $status, $headers);
    }

    /**
     * La página de error. Si falla también ella —una vista recién editada, el disco lleno— la
     * respuesta cae a un HTML mínimo armado acá: el error de un error no puede volver a entrar
     * por este camino ni dejar el cuerpo vacío.
     *
     * @param string|null                                                             $mensaje  el de la HttpException, si lo hay
     * @param array{clase: string, mensaje: string, origen: string, traza: string}|null $detalle sólo en desarrollo
     */
    private function errorHtml(int $status, ?string $mensaje, ?string $referencia, ?array $detalle): string
    {
        [$titulo, $generico] = self::MENSAJES[$status] ?? self::MENSAJES[$status >= 500 ? 500 : 400];

        try {
            return $this->view->render('error', [
                'title' => "{$status} · {$titulo} — PAWPrints",
                'description' => $titulo,
                // Ninguna página del menú está activa: el error pudo pasar en cualquiera.
                'activePage' => '',
                'pageClass' => 'page-error',
                'extraCss' => ['error.css'],
                'status' => $status,
                'titulo' => $titulo,
                'mensaje' => $mensaje ?? $generico,
                'referencia' => $referencia,
                'detalle' => $detalle,
            ]);
        } catch (Throwable $error) {
            $this->logger->critical('Falló la página de error', ['exception' => $error]);

            return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
                . "<title>{$status} — PAWPrints</title></head><body><h1>{$status} · "
                . htmlspecialchars($titulo) . '</h1><p>' . htmlspecialchars($mensaje ?? $generico)
                . '</p><p><a href="/">Volver al inicio</a></p></body></html>';
        }
    }
}
