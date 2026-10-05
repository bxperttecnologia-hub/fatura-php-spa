<?php
class Router {
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, bool $public = false): void {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $re, $handler, $public];
    }

    public function dispatch(string $method, string $path, array $input): array {
        foreach ($this->routes as [$m, $re, $h, $public]) {
            if ($m === $method && preg_match($re, $path, $mt)) {
                if (!$public) Auth::require();
                $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
                return (new $h[0])->{$h[1]}($input, $params);
            }
        }
        throw new HttpException(404, 'Rota não encontrada');
    }
}
