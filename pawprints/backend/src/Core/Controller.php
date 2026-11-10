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

    /** Si la acción pasó por exigirPersonal(): App le agrega Cache-Control: no-store. */
    private bool $soloPersonal = false;

    /**
     * Corta el pedido si no ingresó nadie del personal: lo manda al ingreso, que después lo trae
     * de vuelta acá. Se llama al empezar cada acción del personal.
     *
     * @return array{id: int, usuario: string, nombre: string} quién ingresó
     */
    protected function exigirPersonal(Request $request): array
    {
        $personal = $this->sesion->personal();
        if ($personal === null) {
            // Un GET vuelve a la misma dirección, con su query. Un POST no se puede repetir
            // después del ingreso: vuelve a la página del formulario.
            $volver = $request->path();
            if ($request->method() === 'GET' && $request->queryParams() !== []) {
                $volver .= '?' . http_build_query($request->queryParams());
            }
            throw HttpException::redirect('/personal/ingresar?' . http_build_query(['volver' => $volver]));
        }

        $this->soloPersonal = true;

        return $personal;
    }

    public function soloPersonal(): bool
    {
        return $this->soloPersonal;
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
