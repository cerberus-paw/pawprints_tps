<?php

declare(strict_types=1);

namespace PawPrints;

use LogicException;
use PawPrints\Core\Config;
use PawPrints\Core\Controller;
use PawPrints\Core\HttpException;
use PawPrints\Core\Logger;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Core\Router;
use PawPrints\Core\View;
use Throwable;

/**
 * El recorrido de cada pedido: Request → Router → Controller (Models y View) → Response. Arma
 * las piezas del núcleo una vez y se las pasa a cada controlador, que no crea ninguna por su cuenta.
 */
final class App
{
    public function __construct(
        private readonly Router $router,
        private readonly View $view,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * Si la aplicación ni siquiera arranca (por ejemplo, porque falta config.ini) todavía no hay
     * logger: el motivo va al log de PHP, que con php -S es la terminal, y al cliente sólo el 500.
     */
    public static function run(string $backendDir): void
    {
        try {
            $app = self::build($backendDir);
        } catch (Throwable $error) {
            error_log("PAWPrints no arrancó: {$error}");
            Response::text('Error interno del servidor.', 500)->send();

            return;
        }

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

        return new self($router, new View($backendDir . '/views'), $config, $logger);
    }

    public function handle(Request $request): Response
    {
        $start = hrtime(true);
        // Lo que un controlador imprima por su cuenta (un echo, un aviso de PHP) no puede salir
        // antes de la respuesta: si después falla, se descarta junto con el error; si no, va al
        // principio del cuerpo, para que en desarrollo los avisos se sigan viendo.
        ob_start();
        try {
            [$action, $params] = $this->router->resolve($request->method(), $request->path());
            $response = $this->dispatch($action, $request->withRouteParams($params));
            $stray = (string) ob_get_clean();
            if ($stray !== '') {
                $this->logger->warning('{path} imprimió por fuera de la Response', ['path' => $request->path()]);
                $response = $response->withBody($stray . $response->body());
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
        $response = (new $class($this->view, $this->config, $this->logger))->$method($request);
        if (!$response instanceof Response) {
            throw new LogicException("{$class}::{$method} tiene que devolver una Response.");
        }

        return $response;
    }

    /**
     * El único lugar al que llegan los errores. Por ahora responde el estado con un texto genérico
     * y deja el detalle en el log, así ningún error le muestra al cliente rutas ni código; las
     * páginas de error y qué se muestra en cada entorno son el punto 6.
     */
    private function errorResponse(Throwable $error, Request $request): Response
    {
        if ($error instanceof HttpException) {
            return Response::text($error->getMessage(), $error->status, $error->headers);
        }

        $this->logger->error('Error no controlado en {method} {path}', [
            'method' => $request->method(),
            'path' => $request->path(),
            'exception' => $error,
        ]);

        return Response::text('Error interno del servidor.', 500);
    }
}
