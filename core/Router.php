<?php

/**
 * Router
 * Minimal route table mapping a "route" query param to a
 * [ControllerClass, method] pair. No framework magic — just an array.
 * public/.htaccess rewrites pretty URLs like /admin/branches into
 * index.php?route=admin/branches so this table is the single source
 * of truth for what routes exist.
 */
class Router
{
    private array $routes = [];

    public function get(string $route, array $handler): void
    {
        $this->routes['GET'][$route] = $handler;
    }

    public function post(string $route, array $handler): void
    {
        $this->routes['POST'][$route] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $route  = $_GET['route'] ?? 'who-are-you';

        $handler = $this->routes[$method][$route] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo "404 - Route not found: " . htmlspecialchars($route, ENT_QUOTES, 'UTF-8');
            return;
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();
        $controller->$action();
    }
}
