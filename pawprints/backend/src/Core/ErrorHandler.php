<?php

declare(strict_types=1);

namespace PawPrints\Core;

use ErrorException;
use Throwable;

/**
 * Los errores que no pasan por el try/catch de App::handle(): los avisos de PHP (un índice que no
 * existe, una división por cero), las excepciones que escapan antes o después del pedido y los
 * errores fatales, que cortan el proceso. Sin esto, PHP los imprime en la página con la ruta del
 * archivo y el número de línea; con esto, van al log y el cliente recibe una respuesta genérica.
 * Responsabilidad: Agustina (Punto 6).
 */
final class ErrorHandler
{
    /** Los que matan el proceso: no llegan ni a set_error_handler ni a ningún try/catch. */
    private const FATALES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    /** Aparece recién cuando App leyó la configuración; hasta entonces se usa el log de PHP. */
    private ?Logger $logger = null;

    private function __construct()
    {
    }

    /**
     * Se registra lo antes posible, antes de armar la aplicación: si falta config.ini o una clase
     * del núcleo tiene un error de sintaxis, el error ya tiene que estar tapado.
     */
    public static function register(): self
    {
        $handler = new self();

        // display_errors apagado siempre: cuánto detalle se muestra lo decide la página de error
        // según app.entorno, y el log guarda el error entero. Así tampoco depende del php.ini del
        // servidor donde se publique, que es del punto 7 y no de este repositorio.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler($handler->avisoDePhp(...));
        set_exception_handler($handler->excepcionSuelta(...));
        register_shutdown_function($handler->errorFatal(...));

        return $handler;
    }

    public function conLogger(Logger $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Convierte un aviso de PHP en excepción, para que siga el mismo camino que cualquier otro
     * error: App::handle() lo atrapa y responde la página de error. Sin esto, con display_errors
     * apagado el aviso no se ve y la página sale a medias, y con display_errors prendido se
     * imprime en medio del HTML con la ruta del archivo.
     *
     * @throws ErrorException salvo que el aviso esté silenciado
     */
    public function avisoDePhp(int $severidad, string $mensaje, string $archivo, int $linea): bool
    {
        // Respeta al operador @ y a error_reporting: Config y Logger lo usan a propósito, para
        // encargarse ellos del .ini mal escrito o del log sin permisos de escritura.
        if ((error_reporting() & $severidad) === 0) {
            return false;
        }

        throw new ErrorException($mensaje, 0, $severidad, $archivo, $linea);
    }

    /**
     * Una excepción que no atrapó nadie. Pasa con lo que ocurre fuera del pedido: al armar la
     * aplicación, o al enviar la respuesta ya armada.
     */
    public function excepcionSuelta(Throwable $error, string $mensaje = 'Excepción no atrapada'): void
    {
        $this->registrar($mensaje, $error);
        $this->responder500();
    }

    /**
     * Lo último que corre el proceso. Un fatal —memoria agotada, un require que no existe, un
     * método llamado sobre null— no pasa por ningún catch: PHP lo imprime y termina. Acá se lo
     * reemplaza por el 500 genérico.
     */
    public function errorFatal(): void
    {
        $ultimo = error_get_last();
        if ($ultimo === null || ($ultimo['type'] & self::FATALES) === 0) {
            return;
        }

        $this->registrar(sprintf(
            'Error fatal: %s en %s:%d',
            $ultimo['message'],
            $ultimo['file'],
            $ultimo['line'],
        ));
        $this->responder500();
    }

    /**
     * El 500 de último recurso, en texto plano y sin tocar nada del núcleo: cuando se llega acá,
     * puede haber sido justamente la vista, el autoload o el logger lo que falló, y fallar
     * mientras se responde un error dejaría la respuesta vacía.
     */
    private function responder500(): void
    {
        // Lo que el pedido alcanzó a imprimir antes de romperse no puede quedar pegado al 500.
        // App::handle() abre un búfer por pedido, y la vista abre otro mientras genera el HTML.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Si las cabeceras ya salieron, el estado no se puede cambiar: queda el cuerpo, que al
        // menos no es una página a medias.
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo "Error interno del servidor.\n";
    }

    /**
     * Al log de la aplicación si ya existe y, si no, al de PHP, que con `php -S` es la terminal.
     * En los dos casos queda del lado del servidor: nada de esto va a la respuesta.
     */
    private function registrar(string $mensaje, ?Throwable $error = null): void
    {
        if ($this->logger !== null) {
            $this->logger->critical($mensaje, $error !== null ? ['exception' => $error] : []);

            return;
        }

        error_log($error !== null ? "{$mensaje}: {$error}" : $mensaje);
    }
}
