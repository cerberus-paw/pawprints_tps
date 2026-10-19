<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * Base de los controladores. Cada acción recibe la Request y devuelve una Response; la vista, la
 * configuración, el logger y el mailer se los da App, así ningún controlador los crea por su cuenta.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Config $config,
        protected readonly Logger $logger,
        protected readonly Mailer $mailer,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }
}
