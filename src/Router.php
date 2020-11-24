<?php

namespace AbmmHasan\WebFace;


use AbmmHasan\WebFace\Base\BaseRequest;
use BadMethodCallException;

/**
 * Class Router.
 */
final class Router extends BaseRequest
{
    /**
     * @var array
     */
    private $routes = [];
    /**
     * @var array
     */
    private $baseRoute = [];
    /**
     * @var mixed|null
     */
    private $serverBasePath;
    /**
     * @var string
     */
    private $namespace;
    /**
     * @var
     */
    private $name;
    /**
     * @var
     */
    private $prefix;
    /**
     * @var array
     */
    private $middleware = [];

    private $middlewareCall = 'handle';
    private $middlewareDI = false;

    /**
     * @var string[]
     */
    private $validMethods = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'ANY',
        'AJAX',
        'XPOST',
        'XPUT',
        'XDELETE',
        'XPATCH'
    ];
    /**
     * @var bool
     */
    public $cacheLoaded = false;

    /**
     * Router constructor.
     * @param array $settings
     */
    public function __construct($settings = [], $middleware = [])
    {
        parent::__construct();
        $this->serverBasePath = ($settings['base_path'] ?? $this->url->base);
        $this->namespace = $settings['base_ns'];
        $this->middleware = $middleware;
        if (isset($settings['cache_load']) && $settings['cache_load'] && isset($settings['cache_path'])) {
            $this->cacheLoaded = $this->loadCache($settings['cache_path']);
        }
        if (isset($settings['middleware_di']) && is_bool($settings['middleware_di'])) {
            $this->middlewareDI = $settings['middleware_di'];
        }
    }

    /**
     * @param $method
     * @param $params
     * @return bool
     */
    public function __call($method, $params)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        if (is_null($params) && count($params) !== 2) {
            return false;
        }
        $this->match([$method], $params[0], $params[1]);
    }

    /**
     * @param array $methods
     * @param $route
     * @param $callback
     * @return bool
     */
    public function match(array $methods, $route, $callback)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), $this->validMethods)) {
                throw new BadMethodCallException("Invalid method {$method}.");
            }
        }
        $pattern = self::preparePattern($route);
        $this->buildRoute($methods, $pattern, $callback, $this->routes);
    }

    /**
     * @param array $settings
     * @param $fn
     * @return bool
     */
    public function group(array $settings, $fn)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        // Track current
        $curBaseRoute = $this->baseRoute;
        $curNameSpace = $this->namespace;
        $curMiddleWare = $this->middleware;
        $curName = $this->name;
        $curPrefix = $this->prefix;
        self::prepareGroupContent($settings);

        // Call the callable
        $fn($this);

        // Restore original
        $this->baseRoute = $curBaseRoute;
        $this->namespace = $curNameSpace;
        $this->middleware = $curMiddleWare;
        $this->name = $curName;
        $this->prefix = $curPrefix;
    }

    /**
     * @param null $callback
     * @return bool
     */
    public function run($flash = true)
    {
        $this->runMiddleware($this->middleware['before'] ?? []);
        // Handle all routes
        $numHandled = 0;
        if ($this->xhr) {
            if ($this->method === 'GET' && isset($this->routes["AJAX"])) {
                $numHandled = $this->handle($this->routes["AJAX"]);
            } elseif (isset($this->routes["X" . $this->method])) {
                $numHandled = $this->handle($this->routes["X" . $this->method]);
            }
        } else {
            if (isset($this->routes[$this->method])) {
                $numHandled = $this->handle($this->routes[$this->method]);
            }
            if (!$numHandled && isset($this->routes["ANY"])) {
                $numHandled = $this->handle($this->routes["ANY"]);
            }
        }

        // If no route was handled, trigger the 404 (if any)
        if (!$numHandled) {
            $this->thrownResponse['code'] = 404;
        }
        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }
        $this->runMiddleware($this->middleware['after'] ?? []);
        if ($flash) {
            responseFlush();
        }
        return true;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @return bool
     */
    private function loadCache($path)
    {
        if (file_exists($path)) {
            $this->routes = require($path);
            return true;
        }
        return false;
    }

    /**
     * @param $route
     * @return string
     */
    private function preparePattern($route)
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
    private function buildRoute(array $methods, $pattern, $callback, &$routeResource)
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
                $routeInfo['before'] = self::removeDuplicates($before);
            }
            if (!empty($callback['after'])) {
                $after = array_merge($routeInfo['after'], array_unique($callback['after']));
                $routeInfo['after'] = self::removeDuplicates($after);
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
        foreach ($methods as $method) {
            $routeResource[strtoupper($method)][$pattern] = $routeInfo;
        }
    }

    /**
     * @param $settings
     */
    private function prepareGroupContent($settings)
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

    /**
     * @param $array
     * @return array
     */
    private function removeDuplicates($array)
    {
        return array_keys(
            array_filter(
                array_count_values($array),
                function ($v) {
                    return $v % 2 !== 0;
                }
            )
        );
    }

    /**
     * @param $routes
     * @param false $view
     * @return bool
     */
    private function handle($routes, $view = false)
    {
        // Current Relative URL : remove rewrite base path from it (allows running the router in a sub directory)
        $uri = '/' . trim(substr($this->url->path, strlen($this->serverBasePath)), '/');
        // Check if any exact route exist
        if (isset($routes[$uri])) {
            if (!$this->routeMiddlewareCheck($route['before'], $this->middleware['route'])) {
                return true;
            }
            $this->invoke($routes[$uri]['fn'], $routes[$uri]['namespace']);
            return true;
        }
        // Loop all routes to match route pattern
        foreach ($routes as $storedPattern => $route) {
            $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $storedPattern);
            if (preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                if (!$this->routeMiddlewareCheck($route['before'], $this->middleware['route'])) {
                    return true;
                }
                // Rework matches to only contain the matches, not the original string
                $params = array_column(array_slice($matches[0], 1), 0);
                // Binding key value
                if ($view) {
                    $params = self::mergeKeys($storedPattern, $params);
                }
                $this->invoke($route['fn'], $route['namespace'], $params, $view);
                return true;
            }
        }
        return false;
    }

    private function routeMiddlewareCheck($check, $collection)
    {
        if (empty($check) || empty($collection)) {
            return true;
        }
        $eligible = true;
        if (is_array($collection) && count($collection)) {
            foreach ($check as $middleware) {
                $parameterSeparation = explode(':', $middleware, 2);
                if (isset($collection[$parameterSeparation[0]])) {
                    $eligible = $this->invokeMiddleware($collection[$parameterSeparation[0]], $parameterSeparation[1] ?? '');
                    if (!is_bool($eligible) || $eligible !== true) {
                        $this->thrownResponse['code'] = $eligible['status'] ?? 403;
                        $this->thrownResponse['message'] = $eligible['message'] ?? $eligible;
                        return false;
                    }
                }
            }
        }
        return $eligible;
    }

    private function runMiddleware($resource)
    {
        if (!empty($resource)) {
            foreach ($resource as $execute) {
                $this->invokeMiddleware($execute);
            }
        }
    }

    /**
     * @param $pattern
     * @param $values
     * @return array
     */
    private function mergeKeys($pattern, $values)
    {
        $pattern = preg_replace('/\{(\w+?)\?\}/', '{$1}', $pattern);
        preg_match_all('#\{(!)?(\w+)\}#', $pattern, $matches, PREG_OFFSET_CAPTURE);
        $matches = array_column(array_slice($matches, 2)[0], 0);
        return array_combine($matches, $values);
    }

    /**
     * @param $fn
     * @param string $namespace
     * @param array $params
     */
    private function invoke($fn, $namespace = '', $params = [], $view = false)
    {
        ob_start();
        if ($fn instanceof \Closure) {
            if ($view) {
                initiate($fn, $params)->closure();
            } else {
                initiate($fn, ...$params)->closure();
            }
        } elseif (strpos($fn, '@') !== false) {
            list($controller, $method) = explode('@', $fn, 2);
            if ($namespace !== '') {
                $controller = $namespace . '\\' . $controller;
            }
            if ($view) {
                initiate($controller)->$method($params);
            } else {
                initiate($controller)->$method(...$params);
            }
        }
        ob_end_clean();
    }

    /**
     * @param $fn
     * @param string $namespace
     * @param array $params
     */
    private function invokeMiddleware($fn, $params = '')
    {
        $params = explode(',', $params);
        if ($fn instanceof \Closure) {
            $resource = initiate($fn, ...$params);
            if (!$this->middlewareDI) {
                $resource->_noInject();
            }
            return $resource->closure();
        } else {
            $method = $this->middlewareCall;
            $resource = initiate($fn);
            if (!$this->middlewareDI) {
                $resource->_noInject();
            }
            return $resource->$method(...$params);
        }
    }
}
