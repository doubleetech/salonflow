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
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $route  = $_GET['route'] ?? 'who-are-you';

            $handler = $this->routes[$method][$route] ?? null;

            if (!$handler) {
                http_response_code(404);
                
                // Check if it's an AJAX request
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
                    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Route not found: ' . $route,
                        'code' => 404
                    ]);
                    exit;
                }
                
                // Load 404 error page
                $errorView = __DIR__ . '/../views/errors/404.php';
                if (file_exists($errorView)) {
                    require $errorView;
                } else {
                    echo "404 - Route not found: " . htmlspecialchars($route, ENT_QUOTES, 'UTF-8');
                }
                return;
            }

            [$controllerClass, $action] = $handler;
            
            // Check if controller class exists
            if (!class_exists($controllerClass)) {
                throw new Exception("Controller '$controllerClass' not found");
            }
            
            $controller = new $controllerClass();
            
            // Check if method exists
            if (!method_exists($controller, $action)) {
                throw new Exception("Method '$action' not found in controller '$controllerClass'");
            }
            
            $controller->$action();
            
        } catch (Exception $e) {
            // Let the ErrorHandler handle it
            throw $e;
        }
    }
}