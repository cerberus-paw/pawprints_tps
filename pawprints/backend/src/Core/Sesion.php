<?php

declare(strict_types=1);

namespace PawPrints\Core;

/**
 * La sesión del personal: quién ingresó y el token CSRF de sus formularios. Es lo único del sitio
 * que usa la sesión de PHP; los clientes no tienen usuario.
 *
 * La sesión se abre recién cuando hace falta: al mostrar el formulario de ingreso, al ingresar o
 * cuando el navegador ya trae la cookie. Un cliente que sólo mira el catálogo no recibe ninguna
 * cookie.
 */
final class Sesion
{
    private const COOKIE = 'pawprints_sesion';

    private bool $abierta = false;

    /**
     * @param bool $https si el pedido llegó por HTTPS: la cookie sólo viaja cifrada
     * @param bool $traeCookie si el navegador mandó la cookie de una sesión anterior
     */
    public function __construct(
        private readonly bool $https,
        private readonly bool $traeCookie,
    ) {
    }

    public static function desdeElServidor(): self
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';

        return new self($https, isset($_COOKIE[self::COOKIE]));
    }

    /**
     * Quién del personal ingresó, o null.
     *
     * @return array{id: int, usuario: string, nombre: string}|null
     */
    public function personal(): ?array
    {
        if (!$this->abrirSiHay()) {
            return null;
        }

        /** @var array{id: int, usuario: string, nombre: string}|null */
        return $_SESSION['personal'] ?? null;
    }

    /**
     * Deja ingresada a esa persona. El id de la sesión cambia: uno que alguien haya conseguido
     * antes del ingreso (fijación de sesión) no sirve para entrar después. También cambia el
     * token CSRF, por el mismo motivo.
     *
     * @param array{id: int, usuario: string, nombre: string} $personal
     */
    public function ingresar(array $personal): void
    {
        $this->abrir();
        session_regenerate_id(true);
        $_SESSION = ['personal' => $personal, 'csrf' => self::tokenNuevo()];
    }

    /** Termina la sesión: se borran los datos del servidor y la cookie del navegador. */
    public function salir(): void
    {
        if (!$this->abrirSiHay()) {
            return;
        }

        $_SESSION = [];
        session_destroy();
        $this->abierta = false;
        // Los mismos parámetros con los que se creó, para que el navegador la reconozca, pero
        // vencida. setcookie pide «expires» en lugar del «lifetime» de la sesión.
        $parametros = $this->parametrosCookie();
        unset($parametros['lifetime']);
        setcookie(self::COOKIE, '', ['expires' => 1] + $parametros);
    }

    /**
     * El token que lleva cada formulario del personal en un campo oculto. Otro sitio puede hacer
     * que el navegador mande un POST con la cookie de la sesión, pero no puede leer este valor,
     * así que su pedido llega sin él y se rechaza.
     */
    public function csrf(): string
    {
        $this->abrir();

        return $_SESSION['csrf'] ??= self::tokenNuevo();
    }

    public function csrfValido(?string $token): bool
    {
        if ($token === null || $token === '' || !$this->abrirSiHay()) {
            return false;
        }

        // hash_equals compara en tiempo constante: cuánto tarda no dice cuántos caracteres
        // acertó quien prueba tokens.
        return is_string($_SESSION['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $token);
    }

    /** Abre la sesión sólo si el navegador ya traía la cookie. */
    private function abrirSiHay(): bool
    {
        if ($this->abierta) {
            return true;
        }
        if (!$this->traeCookie) {
            return false;
        }
        $this->abrir();

        return true;
    }

    private function abrir(): void
    {
        if ($this->abierta) {
            return;
        }

        session_name(self::COOKIE);
        session_set_cookie_params($this->parametrosCookie());
        session_start([
            // Un id que no creó el servidor no se acepta: no se puede inventar uno y esperar a
            // que la víctima ingrese con él.
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            // Ni en la URL ni en los enlaces: el id de sesión es como una contraseña.
            'use_trans_sid' => false,
        ]);
        $this->abierta = true;
    }

    /** @return array{lifetime: int, path: string, secure: bool, httponly: bool, samesite: string} */
    private function parametrosCookie(): array
    {
        return [
            // Hasta que se cierra el navegador.
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->https,
            // El JavaScript de la página no la puede leer: un XSS no se lleva la sesión.
            'httponly' => true,
            // No viaja en los POST que vienen de otro sitio. Es la primera barrera contra el
            // CSRF; el token es la segunda.
            'samesite' => 'Lax',
        ];
    }

    private static function tokenNuevo(): string
    {
        return bin2hex(random_bytes(32));
    }
}
