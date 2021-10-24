<?php

namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Base\BaseRoute;
use AbmmHasan\WebFace\Support\ResponseDepot;
use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Utility\URL;
use BadMethodCallException;

/**
 * Class Router.
 */
final class Router extends BaseRoute
{
    /**
     * Router constructor.
     * @param array $middleware
     * @param bool $loadCache
     */
    public function __construct(array $middleware = [], bool $loadCache = true)
    {
        parent::__construct();
        $this->serverBasePath = empty(Settings::$base_path) ? URL::get('base') : Settings::$base_path;
        $this->globalMiddleware = $middleware;
        if ($loadCache && Settings::$cache_load && !empty(Settings::$cache_path)) {
            $this->cacheLoaded = $this->loadCache();
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
        $curMiddleWare = $this->middleware;
        $curName = $this->name;
        $curPrefix = $this->prefix;
        self::prepareGroupContent($settings);

        // Call the callable
        $fn($this);

        // Restore original
        $this->baseRoute = $curBaseRoute;
        $this->middleware = $curMiddleWare;
        $this->name = $curName;
        $this->prefix = $curPrefix;
    }

    /**
     * @param bool $flash
     * @return bool
     */
    public function run($flash = true)
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }
        $this->runMiddleware($this->globalMiddleware['before'] ?? []);
        // Handle all routes
        $numHandled = 0;
        $method = URL::getMethod('converted');
        if (URL::getMethod('isAjax')) {
            if ($method === 'GET' && isset($this->routes["AJAX"])) {
                $numHandled = $this->handle($this->routes["AJAX"], 'AJAX');
            } elseif (isset($this->routes["X" . $method])) {
                $numHandled = $this->handle($this->routes["X" . $method], "X" . $method);
            }
        } else {
            if (isset($this->routes[$method])) {
                $numHandled = $this->handle($this->routes[$method], $method);
            }
            if (!$numHandled && isset($this->routes["ANY"])) {
                $numHandled = $this->handle($this->routes["ANY"], 'ANY');
            }
        }

        // If no route was handled, trigger the 404 (if any)
        if (!$numHandled) {
            ResponseDepot::$code = 404;
        }
        $this->runMiddleware($this->globalMiddleware['after'] ?? []);
        if ($flash) {
            responseFlush();
        }
        return ResponseDepot::$code;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
