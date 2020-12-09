<?php

namespace AbmmHasan\WebFace;


use AbmmHasan\WebFace\Base\BaseRequest;
use AbmmHasan\WebFace\Base\BaseRoute;
use BadMethodCallException;

/**
 * Class Router.
 */
final class Router extends BaseRoute
{
    /**
     * Router constructor.
     * @param array $settings
     */
    public function __construct($settings = [], $middleware = [])
    {
        parent::__construct();
        $this->serverBasePath = ($settings['base_path'] ?? $this->url->base);
        $this->namespace = $settings['base_ns'];
        $this->globalMiddleware = $middleware;
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
     * @param bool $flash
     * @return bool
     */
    public function run($flash = true)
    {
        $this->runMiddleware($this->globalMiddleware['before'] ?? []);
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
        $this->runMiddleware($this->globalMiddleware['after'] ?? []);
        if ($flash) {
            responseFlush();
        }
        return $this->thrownResponse;
    }

    /**
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}
