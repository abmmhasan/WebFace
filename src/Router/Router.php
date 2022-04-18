<?php

namespace AbmmHasan\WebFace\Router;

use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\BaseRoute;
use AbmmHasan\WebFace\Router\Asset\Settings;
use AbmmHasan\WebFace\Support\ResponseDepot;
use BadMethodCallException;
use Exception;
use ReflectionException;
use function responseFlush;

/**
 * Class Router.
 */
final class Router extends BaseRoute
{
    /**
     * Router constructor.
     *
     * @param array $middleware
     * @param bool $loadCache
     */
    public function __construct(array $middleware = [], bool $loadCache = true)
    {
        parent::__construct();
        $this->serverBasePath = Settings::$basePath ?? URL::get('base');
        $this->globalMiddleware = $middleware;
        if ($loadCache && Settings::$cacheLoad && !empty(Settings::$cachePath)) {
            $this->cacheLoaded = $this->loadCache();
        }
    }

    /**
     * Add route method by match
     *
     * @param $method
     * @param $params
     * @return bool|void
     */
    public function __call($method, $params)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        if (empty($params) || count($params) !== 2) {
            return false;
        }
        $this->match([$method], $params[0], $params[1]);
    }

    /**
     * Add route
     *
     * @param array $methods
     * @param string $route
     * @param array|callable $callback
     * @return bool|void
     */
    public function match(array $methods, string $route, array|callable $callback)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        foreach ($methods as $method) {
            if (!in_array(strtoupper($method), $this->validMethods)) {
                throw new BadMethodCallException("Invalid method $method.");
            }
        }
        $this->buildRoute(
            $methods,
            $this->preparePattern($route),
            $callback,
            $this->routes
        );
    }

    /**
     * Add route group
     *
     * @param array $settings
     * @param $fn
     * @return bool|void
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
        $this->prepareGroupContent($settings);

        // Call the callable
        $fn($this);

        // Restore original
        $this->baseRoute = $curBaseRoute;
        $this->middleware = $curMiddleWare;
        $this->name = $curName;
        $this->prefix = $curPrefix;
    }

    /**
     * Run router
     *
     * @return bool|int
     * @throws Exception|ReflectionException
     */
    public function run(): bool|int
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }
        $this->runMiddleware($this->globalMiddleware['before'] ?? []);
        // Handle all routes
        $numHandled = false;
        $method = URL::getMethod('converted');
        if (!URL::getMethod('isAjax')) {
            if (isset($this->routes[$method])) {
                $numHandled = $this->handle($this->routes[$method], $method);
            }
            if (!$numHandled && isset($this->routes["ANY"])) {
                $numHandled = $this->handle($this->routes["ANY"], 'ANY');
            }
        } elseif (isset($this->routes["X" . $method])) {
            $numHandled = $this->handle($this->routes["X" . $method], "X" . $method);
        }

        // If no route was handled, trigger the 404 (if any)
        if (!$numHandled) {
            ResponseDepot::setStatus(404);
        }
        $this->runMiddleware($this->globalMiddleware['after'] ?? []);
        responseFlush();
        return ResponseDepot::getStatus();
    }

    /**
     * Get all the available routes
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
