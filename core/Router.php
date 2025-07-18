<?php
class Router
{
    private $routes = [];
    private $prefix = '';
    private $middlewares = [];

    public function __construct($basePath = '')
    {
        $this->prefix = $basePath;
    }

    public function get($path, $handler, $middlewares = [])
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post($path, $handler, $middlewares = [])
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put($path, $handler, $middlewares = [])
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete($path, $handler, $middlewares = [])
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function patch($path, $handler, $middlewares = [])
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function group($options, $callback)
    {
        // Sauvegarde les middlewares globaux
        $previousMiddlewares = $this->middlewares;

        // Ajoute les nouveaux middlewares du groupe
        if (isset($options['middleware'])) {
            $this->middlewares = array_merge($this->middlewares, (array)$options['middleware']);
        }

        // Sauvegarde le préfixe précédent
        $previousPrefix = $this->prefix;

        // Ajoute le nouveau préfixe
        if (isset($options['prefix'])) {
            $this->prefix = rtrim($previousPrefix, '/') . '/' . ltrim($options['prefix'], '/');
        }

        // Exécute le callback avec les nouvelles configurations
        call_user_func($callback, $this);

        // Restaure les configurations précédentes
        $this->middlewares = $previousMiddlewares;
        $this->prefix = $previousPrefix;
    }

    private function addRoute($method, $path, $handler, $routeMiddlewares = [])
    {
        // Combine les middlewares globaux et spécifiques à la route
        $middlewares = array_merge($this->middlewares, (array)$routeMiddlewares);

        // Construit le chemin complet avec le préfixe
        $fullPath = rtrim($this->prefix, '/') . '/' . ltrim($path, '/');
        $fullPath = $fullPath === '/' ? '/' : rtrim($fullPath, '/');

        $this->routes[$method][$fullPath] = [
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);

        $basePath = '/assurbrokers-api';
        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        $path = $path ?: '/';

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routePath => $route) {
                $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $routePath);
                $pattern = "#^" . $pattern . "$#";

                if (preg_match($pattern, $path, $matches)) {
                    array_shift($matches); 

                    return $this->handleRoute($route, $matches);
                }
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route non trouvée', 'path' => $path]);
    }


    private function handleRoute($route, $params = [])
    {
        // Exécute les middlewares
        foreach ($route['middlewares'] as $middleware) {
            $this->executeMiddleware($middleware);
        }

        // Exécute le handler
        $handler = $route['handler'];

        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controllerName, $methodName) = explode('@', $handler);

            $controllerFile = __DIR__ . "/../controllers/{$controllerName}.php";
            if (!file_exists($controllerFile)) {
                throw new Exception("Controller $controllerName not found");
            }

            require_once $controllerFile;

            if (!class_exists($controllerName)) {
                throw new Exception("Class $controllerName not found");
            }

            $controller = new $controllerName();

            if (!method_exists($controller, $methodName)) {
                throw new Exception("Method $methodName not found in $controllerName");
            }

            return call_user_func_array([$controller, $methodName], $params);
        }

        throw new Exception("Invalid route handler");
    }


    private function executeMiddleware($middleware)
    {
        if (is_callable($middleware)) {
            return call_user_func($middleware);
        }

        if (is_string($middleware) && class_exists($middleware)) {
            $middlewareInstance = new $middleware();
            if (method_exists($middlewareInstance, 'handle')) {
                return $middlewareInstance->handle();
            }
        }

        throw new Exception("Invalid middleware: " . print_r($middleware, true));
    }
}
