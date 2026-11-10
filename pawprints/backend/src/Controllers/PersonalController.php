<?php

declare(strict_types=1);

namespace PawPrints\Controllers;

use PawPrints\Core\Controller;
use PawPrints\Core\Request;
use PawPrints\Core\Response;
use PawPrints\Models\PersonalRepository;

/**
 * El ingreso y la salida del personal (TP5, consigna 3). No hay registro: los usuarios se crean
 * con bin/crear-personal.php.
 */
class PersonalController extends Controller
{
    /** Adónde va quien ingresa, si no venía de otra página del personal. */
    private const INICIO = '/libros/nuevo';

    /**
     * El hash de una contraseña al azar que nadie conoce, para que el ingreso con un usuario que no
     * existe tarde lo mismo que con uno que existe: si no, cuánto tarda la respuesta diría qué
     * usuarios hay. Tiene que ser un hash de verdad, con el mismo costo que los de password_hash:
     * con uno mal formado, password_verify contesta enseguida.
     */
    private const HASH_DE_RELLENO = '$2y$12$ZOPSjR159naGBk3RlCAiculVZOio0k4m9OSb7TOBledQkCOl6TyAS';

    private ?PersonalRepository $personal = null;

    /** GET /personal/ingresar */
    public function formulario(Request $request): Response
    {
        if ($this->sesion->personal() !== null) {
            return Response::redirect(self::INICIO, 303);
        }

        return $this->mostrar('');
    }

    /** POST /personal/ingresar */
    public function ingresar(Request $request): Response
    {
        $this->exigirCsrf($request);

        $usuario = strtolower(trim((string) $request->input('usuario', '')));
        $clave = (string) $request->input('clave', '');

        $persona = $usuario === '' ? null : $this->personal()->porUsuario($usuario);
        $correcta = password_verify($clave, $persona['clave_hash'] ?? self::HASH_DE_RELLENO);

        if ($persona === null || !$correcta) {
            // El usuario sí, para ver si alguien prueba claves contra una cuenta; la contraseña
            // nunca, ni siquiera la equivocada, que suele ser casi la correcta.
            $this->logger->notice('Ingreso fallido del personal: usuario «{usuario}»', ['usuario' => $usuario]);

            // Un solo mensaje para los dos casos: no dice si el usuario existe.
            return $this->mostrar($usuario, 'Usuario o contraseña incorrectos.', 422);
        }

        // Si PHP cambió el algoritmo recomendado desde que se creó la contraseña, se aprovecha que
        // ahora se tiene en claro para guardarla con el nuevo.
        if (password_needs_rehash($persona['clave_hash'], PASSWORD_DEFAULT)) {
            $this->personal()->cambiarHash($persona['id'], password_hash($clave, PASSWORD_DEFAULT));
        }

        $this->sesion->ingresar([
            'id' => $persona['id'],
            'usuario' => $persona['usuario'],
            'nombre' => $persona['nombre'],
        ]);
        $this->logger->info('Ingresó del personal: {usuario}', ['usuario' => $persona['usuario']]);

        return Response::redirect(self::INICIO, 303);
    }

    /**
     * POST /personal/salir — es un POST con token y no un enlace: si fuera un GET, cualquier
     * página podría cerrarle la sesión a alguien con una imagen que apunte acá.
     */
    public function salir(Request $request): Response
    {
        $this->exigirCsrf($request);
        $this->sesion->salir();

        return Response::redirect('/', 303);
    }

    private function mostrar(string $usuario, ?string $error = null, int $estado = 200): Response
    {
        return $this->render('personal/ingresar', [
            'title' => 'Ingreso del personal — PAWPrints',
            'description' => 'Ingreso del personal de PAWPrints para ver los pedidos y cargar libros.',
            'activePage' => '',
            'pageClass' => 'page-personal',
            'extraCss' => ['personal.css'],
            'usuario' => $usuario,
            'error' => $error,
            'csrf' => $this->sesion->csrf(),
        ], $estado);
    }

    private function personal(): PersonalRepository
    {
        return $this->personal ??= new PersonalRepository($this->db);
    }
}
