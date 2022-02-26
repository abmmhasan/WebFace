<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\OOF\DI\Container;
use AbmmHasan\WebFace\Support\ResponseDepot;
use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Support\RouteDepot;
use AbmmHasan\WebFace\Utility\URL;

abstract class BaseRoute
{
    protected array $routes = [];
    protected array $baseRoute = [];
    protected string $serverBasePath;
    protected $name;
    protected $prefix;
    protected array $middleware = [];
    protected array $globalMiddleware = [];
    protected array $validMethods = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'OPTIONS',
        'PATCH',
        'ANY',
        'AJAX',
        'XPOST',
        'XPUT',
        'XDELETE',
        'XPATCH',
        'VIEW'
    ];
    public bool $cacheLoaded = false;

    /**
     * Base Route Constructor
     */
    public function __construct()
    {
        $this->loadJsonSettings();
    }

    /**
     * Load Settings from json file
     *
     * @return void
     */
    private function loadJsonSettings()
    {
        if (file_exists($file = projectPath() . 'webface.json')) {
            $json = json_decode(file_get_contents($file));
            if (!empty($json)) {
                foreach ($json as $item => $value) {
                    Settings::$$item = $value;
                }
            }
        }
    }

    /**
     * Load route cache
     *
     * @return bool
     */
    protected function loadCache(): bool
    {
        $cachePath = projectPath() . Settings::$cache_path;
        if (file_exists($cachePath)) {
            $this->routes = require($cachePath);
            return true;
        }
        return false;
    }

    /**
     * Prepare route pattern (for further process)
     *
     * @param $route
     * @return string
     */
    protected function preparePattern($route): string
    {
        $route = array_filter(explode('/', trim($route)));
        $route = array_merge($this->baseRoute, $route);
        return '/' . implode('/', $route);
    }

    /**
     * Prepare route asset (for further process)
     *
     * @param array $methods
     * @param $pattern
     * @param $callback
     * @param $routeResource
     */
    protected function buildRoute(array $methods, $pattern, $callback, &$routeResource)
    {
        $routeInfo = [
            'before' => $this->middleware['before'] ?? [],
            'after' => $this->middleware['after'] ?? [],
            'fn' => $callback
        ];
        if (is_array($callback)) {
            if (!empty($callback['middleware']) || !empty($callback['before'])) {
                $before = array_merge(
                    $routeInfo['before'],
                    array_unique($callback['before'] ?? $callback['middleware'])
                );
                $routeInfo['before'] = $this->filterMiddlewareTag($before);
            }
            if (!empty($callback['after'])) {
                $after = array_merge($routeInfo['after'], array_unique($callback['after']));
                $routeInfo['after'] = $this->filterMiddlewareTag($after);
            }
            if (!empty($callback['name'])) {
                $nameFix = explode('.', $callback['name']);
                if (!empty($this->name)) {
                    $nameFix = array_merge(explode('.', $this->name) ?? [], $nameFix);
                }
                $routeInfo['name'] = implode('.', $nameFix);
            }
            $routeInfo['fn'] = $callback['uses'];
        }
        $routeInfo['name'] ??= implode('.',
            array_filter(
                explode('/',
                    str_replace(['{', '}'], '', $pattern)
                )
            )
        );
        foreach ($methods as $method) {
            $routeResource['named'][$routeInfo['name']] ??= [$method, $pattern];
            $routeResource['list'][] = $pattern;
            $routeResource[strtoupper($method)][$pattern] = $routeInfo;
        }
    }

    /**
     * Prepare grouped routes
     *
     * @param $settings
     */
    protected function prepareGroupContent($settings)
    {
        if (!empty($settings['prefix'])) {
            $this->prefix = explode('/', trim($settings['prefix']));
            $this->baseRoute = array_filter(array_merge($this->baseRoute, $this->prefix));
        }
        if (!empty($settings['middleware']) || !empty($settings['before'])) {
            $this->middleware['before'] = array_merge(
                $this->middleware['before'] ?? [],
                array_unique($settings['before'] ?? $settings['middleware'])
            );
        }
        if (!empty($settings['after'])) {
            $this->middleware['after'] = array_merge(
                $this->middleware['after'] ?? [],
                array_unique($settings['after'])
            );
        }
        if (!empty($settings['name'])) {
            $nameFix = explode('.', $settings['name']);
            if (!empty($this->name)) {
                $nameFix = array_merge(explode('.', $this->name), $nameFix);
            }
            $this->name = implode('.', $nameFix);
        }
    }

    /**
     * Execute middlewares
     *
     * @param $resource
     * @return void
     */
    protected function runMiddleware($resource)
    {
        if (!empty($resource)) {
            foreach ($resource as $execute) {
                $this->invokeMiddleware($execute);
            }
        }
    }

    /**
     * Find and execute exact route
     *
     * @param $routes
     * @param $method
     * @return bool
     */
    protected function handle($routes, $method): bool
    {
        // Current Relative URL: remove rewrite base path from it (allows running the router in a subdirectory)
        $uri = '/' . trim(substr(URL::get('path'), strlen($this->serverBasePath)), '/');
        // absolute match
        if (isset($routes[$uri])) {
            RouteDepot::setCurrentRoute($method . ' ' . $uri);
            if (!$this->routeMiddlewareCheck($routes[$uri]['before'] ?? [], $this->globalMiddleware['route'])) {
                return true;
            }
            $this->invoke($routes[$uri]['fn']);
            $this->runMiddleware($routes[$uri]['after'] ?? []);
            return true;
        }
        // pattern match
        foreach ($routes as $storedPattern => $route) {
            if (str_contains($storedPattern, '{')) {
                $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $storedPattern);
                if (preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_SET_ORDER)) {
                    RouteDepot::setCurrentRoute($method . ' ' . $storedPattern);
                    if (!$this->routeMiddlewareCheck($route['before'] ?? [], $this->globalMiddleware['route'])) {
                        return true;
                    }
                    preg_match_all('#^' . $pattern . '$#', $storedPattern, $patternKeys, PREG_SET_ORDER);
                    unset($patternKeys[0][0], $matches[0][0]);
                    $this->invoke($route['fn'], array_combine(
                        array_map(function ($value) {
                            return trim($value, "{}");
                        }, $patternKeys[0]),
                        $matches[0]
                    ));
                    $this->runMiddleware($route['after'] ?? []);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Middleware tag filter (add/remove middleware based on given condition)
     *
     * @param $array
     * @return array
     */
    private function filterMiddlewareTag($array): array
    {
        $result = [];
        if (!empty($array)) {
            foreach ($array as $item) {
                if (!empty($item) &&
                    !in_array($item, $result) &&
                    !in_array("!{$item}", $array) &&
                    !str_starts_with($item, '!')) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    /**
     * Checks route middleware for generating permission
     *
     * @param $check
     * @param $collection
     * @return bool
     */
    private function routeMiddlewareCheck($check, $collection): bool
    {
        if (empty($check) || empty($collection)) {
            return true;
        }
        if (is_array($collection) && count($collection)) {
            foreach ($check as $middleware) {
                $parameterSeparation = explode(':', $middleware, 2);
                if (isset($collection[$parameterSeparation[0]])) {
                    $eligible = $this->invokeMiddleware($collection[$parameterSeparation[0]], $parameterSeparation[1] ?? '');
                    if ($eligible !== true) {
                        ResponseDepot::$code = $eligible['status'] ?? 403;
                        ResponseDepot::$content = [
                            'status' => 'failed',
                            'message' => $eligible['message'] ?? (is_string($eligible) ? $eligible : 'Bad Request')
                        ];
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Invoke method
     *
     * @param $fn
     * @param array $params
     */
    private function invoke($fn, array $params = [])
    {
        ob_start();
        if ($fn instanceof \Closure) {
            Container::registerClosure('1', $fn, $params)
                ->callClosure('1');
        } elseif (is_array($fn)) {
            [$controller, $method] = $fn;
            Container::registerMethod($controller, $method, $params)
                ->callMethod($controller);
        }
        ob_end_clean();
    }

    /**
     * Invoke middleware
     *
     * @param $fn
     * @param string $params
     * @return mixed|void
     */
    private function invokeMiddleware($fn, string $params = '')
    {
        $params = array_filter(explode(',', $params));
        if ($fn instanceof \Closure) {
            return Container::registerClosure('1', $fn, $params)
                ->callClosure('1');
        }
        return Container::registerMethod($fn, Settings::$middleware_call_on_method, $params)
            ->callMethod($fn);
    }
}
