<?php

namespace PawPrints\Router;

use PawPrints\Controllers\HomeController;
use PawPrints\Controllers\SobreNosotrosController;

/**
 * Enrutador básico de la aplicación.
 * Mapea las peticiones entrantes a su respectivo controlador y acción.
 */
class Router
{
    public function dispatch(): void
    {
        $action = $_GET['action'] ?? null;

        if (!$action) {
            $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $uriPath = trim($uriPath, '/');

            // Extraemos los segmentos de la ruta
            $segments = explode('/', $uriPath);
            $lastSegment = end($segments);

            if ($lastSegment === '' || $lastSegment === 'index.php' || $lastSegment === 'pawprints') {
                $action = 'inicio';
            } else {
                $action = str_replace('.php', '', $lastSegment);
            }
        }

        switch ($action) {
            case 'inicio':
            case 'home':
            case '':
                $controller = new HomeController();
                $controller->index();
                break;

            case 'sobre-nosotros':
            case 'about':
                $controller = new SobreNosotrosController();
                $controller->index();
                break;

            // Enrutamiento provisorio hacia los maquetados para facilitar la navegación
            // mientras Agustina y Mateo desarrollan sus respectivos módulos.
            case 'catalogo':
                if (file_exists(__DIR__ . '/../../frontend/catalogo.html')) {
                    header('Location: frontend/catalogo.html');
                    exit;
                }
                break;

            case 'reserva':
                if (file_exists(__DIR__ . '/../../frontend/reserva.html')) {
                    header('Location: frontend/reserva.html');
                    exit;
                }
                break;

            case 'detalle-libro':
                $id = $_GET['id'] ?? '';
                if (file_exists(__DIR__ . '/../../frontend/detalle-libro.html')) {
                    header('Location: frontend/detalle-libro.html' . ($id !== '' ? '?id=' . urlencode($id) : ''));
                    exit;
                }
                break;

            default:
                http_response_code(404);
                echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>404 No Encontrado</title></head><body><h1>404 — Página no encontrada</h1><p><a href="index.php">Volver al inicio</a></p></body></html>';
                break;
        }
    }
}
