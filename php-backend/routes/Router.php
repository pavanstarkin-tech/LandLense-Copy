<?php
namespace LandLens\Routes;

class Router {
    private array $routes = [];

    public function add(string $method, string $path, $handler) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function get(string $path, $handler) {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, $handler) {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, $handler) {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, $handler) {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normalize trailing slash (unless root)
        if ($requestUri !== '/' && str_ends_with($requestUri, '/')) {
            $requestUri = rtrim($requestUri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod && $route['method'] !== 'ANY') {
                continue;
            }

            // Convert route pattern {id} to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $requestUri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    return call_user_func_array($handler, array_values($params));
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                    $controller = is_string($class) ? new $class() : $class;
                    return call_user_func_array([$controller, $method], array_values($params));
                }
            }
        }

        // 404 fallback
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 404,
            'error' => 'Not Found',
            'message' => "Endpoint not found: {$requestMethod} {$requestUri}"
        ]);
        exit();
    }
}
