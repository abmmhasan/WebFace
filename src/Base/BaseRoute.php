<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\OOF\DI\Container;
use AbmmHasan\WebFace\Request;
use AbmmHasan\WebFace\Support\ResponseDepot;
use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Support\Storage;
use AbmmHasan\WebFace\Utility\URL;

abstract class BaseRoute
{
    protected $routes = [];
    protected $baseRoute = [];
    protected $serverBasePath;
    protected $namespace;
    protected $name;
    protected $prefix;
    protected $middleware = [];
    protected $globalMiddleware = [];
    private $middlewareCall = 'handle';
    protected $validMethods = [
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
    public $cacheLoaded = false;

    public function __construct()
    {
        $this->loadJsonSettings();
    }

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
     * @param $route
     * @return string
     */
    protected function preparePattern($route)
    {
        $route = array_filter(explode('/', trim($route)));
        $route = array_merge($this->baseRoute, $route);
        return '/' . implode('/', $route);
    }

    /**
     * @param array $methods
     * @param $pattern
     * @param $callback
     * @param $routeResource
     */
    protected function buildRoute(array $methods, $pattern, $callback, &$routeResource)
    {
        $routeInfo = [
            'namespace' => $this->namespace,
            'before' => $this->middleware['before'] ?? [],
            'after' => $this->middleware['after'] ?? [],
            'fn' => $callback
        ];
        if (is_array($callback)) {
            if (!empty($callback['namespace'])) {
                $routeInfo['namespace'] = $this->namespace . '\\' . ucwords(
                        $callback['namespace']
                    );
            }
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
     * @param $settings
     */
    protected function prepareGroupContent($settings)
    {
        if (!empty($settings['prefix'])) {
            $this->prefix = explode('/', trim($settings['prefix']));
            $this->baseRoute = array_filter(array_merge($this->baseRoute, $this->prefix));
        }
        if (!empty($settings['namespace'])) {
            $this->namespace .= '\\' . ucwords($settings['namespace'], '\\');
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

    protected function runMiddleware($resource)
    {
        if (!empty($resource)) {
            foreach ($resource as $execute) {
                $this->invokeMiddleware($execute);
            }
        }
    }

    /**
     * @param $routes
     * @param false $view
     * @return bool
     */
    protected function handle($routes, $method, $view = false)
    {
        // Current Relative URL : remove rewrite base path from it (allows running the router in a sub directory)
        $uri = '/' . trim(substr(URL::get('path'), strlen($this->serverBasePath)), '/');
        // Check if any exact route exist
        if (isset($routes[$uri])) {
            Storage::setCurrentRoute($method . ' ' . $uri);
            if (!$this->routeMiddlewareCheck($routes[$uri]['before'], $this->globalMiddleware['route'])) {
                return true;
            }
            $this->invoke($routes[$uri]['fn'], $routes[$uri]['namespace']);
            $this->runMiddleware($route['after'] ?? []);
            return true;
        }
        // Loop all routes to match route pattern
        foreach ($routes as $storedPattern => $route) {
            $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $storedPattern);
            if (preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_SET_ORDER)) {
                Storage::setCurrentRoute($method . ' ' . $storedPattern);
                if (!$this->routeMiddlewareCheck($route['before'], $this->globalMiddleware['route'])) {
                    return true;
                }
                preg_match_all('#^' . $pattern . '$#', $storedPattern, $patternKeys, PREG_SET_ORDER);
                unset($patternKeys[0][0], $matches[0][0]);
                $this->invoke($route['fn'], $route['namespace'], array_combine(
                    array_map(function ($value) {
                        return trim($value, "{}");
                    }, $patternKeys[0]),
                    $matches[0]
                ));
                $this->runMiddleware($route['after'] ?? []);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $array
     * @return array
     */
    private function filterMiddlewareTag($array)
    {
        $result = [];
        if (!empty($array)) {
            foreach ($array as $item) {
                if (!empty($item) &&
                    !in_array($item, $result) &&
                    !in_array("!{$item}", $array) &&
                    strpos($item, '!') === false) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    private function routeMiddlewareCheck($check, $collection)
    {
        $eligible = true;
        if (empty($check) || empty($collection)) {
            return $eligible;
        }
        if (is_array($collection) && count($collection)) {
            foreach ($check as $middleware) {
                $parameterSeparation = explode(':', $middleware, 2);
                if (isset($collection[$parameterSeparation[0]])) {
                    $eligible = $this->invokeMiddleware($collection[$parameterSeparation[0]], $parameterSeparation[1] ?? '');
                    if (!is_bool($eligible) || $eligible !== true) {
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
        return $eligible;
    }

    /**
     * @param $fn
     * @param string $namespace
     * @param array $params
     */
    private function invoke($fn, $namespace = '', $params = [])
    {
        ob_start();
        if ($fn instanceof \Closure) {
            Container::registerClosure('1', $fn, $params)
                ->callClosure('1');
        } elseif (str_contains($fn, '@')) {
            list($controller, $method) = explode('@', $fn, 2);
            if ($namespace !== '') {
                $controller = $namespace . '\\' . $controller;
            }
            Container::registerMethod($controller, $method, $params)
                ->callMethod($controller);
        }
        ob_end_clean();
    }

    /**
     * @param $fn
     * @param string $params
     * @return
     */
    private function invokeMiddleware($fn, $params = '')
    {
        $params = array_filter(explode(',', $params));
        if ($fn instanceof \Closure) {
            Container::registerClosure('1', $fn, $params)
                ->callClosure('1');
        } else {
            $method = $this->middlewareCall;
            return Container::registerMethod($fn, $method, $params)
                ->callMethod($fn);
        }
    }
}
