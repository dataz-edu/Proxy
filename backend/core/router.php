<?php
class Router
{
    private $routes = [];

    public function add($method, $path, callable $handler)
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch($method, $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && $route['path'] === $path) {
                call_user_func($route['handler']);
                return;
            }
        }
        Response::json(['message' => 'Not Found'], 404);
    }
}
