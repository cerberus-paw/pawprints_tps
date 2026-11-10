<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * Base de los controladores. Cada acción recibe la Request y devuelve una Response; la vista, la
 * configuración, el logger, el mailer, la base y la sesión se los da App, así ningún controlador
 * los crea por su cuenta.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Config $config,
        protected readonly Logger $logger,
        protected readonly Mailer $mailer,
        protected readonly Database $db,
        protected readonly Sesion $sesion,
    ) {
    }

    /**
     * Corta el pedido con un 400 si el formulario no trae el token CSRF de la sesión: el POST no
     * salió de una página del sitio.
     */
    protected function exigirCsrf(Request $request): void
    {
        if (!$this->sesion->csrfValido($request->input('csrf'))) {
            $this->logger->notice('POST {path} rechazado: token CSRF inválido', ['path' => $request->path()]);
            throw HttpException::badRequest('El formulario venció o no salió de este sitio. Volvé a la página y probá de nuevo.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }
}
