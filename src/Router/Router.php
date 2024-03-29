<?php

namespace AbmmHasan\WebFace\Router;

use AbmmHasan\WebFace\Response\Asset\Repository;
use AbmmHasan\WebFace\Router\Asset\BaseRoute;
use AbmmHasan\WebFace\Router\Asset\Invoke;
use AbmmHasan\WebFace\Router\Asset\Settings;
use BadMethodCallException;
use Error;
use Exception;
use ReflectionException;
use function responseFlush;

/**
 * Class Router.
 */
class Router extends BaseRoute
{
    /**
     * Router constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->patternKeys = array_keys($this->pattern);
        if (Settings::$cacheLoad && !empty(Settings::$cachePath)) {
            $this->cacheLoaded = $this->loadCache();
        }
    }

    /**
     * Set middleware classes
     *
     * @param array $route on-route & per-route; alias => class
     * @param array $before list of class executed before route execution
     * @param array $after list of class executed after route execution
     * @return $this
     */
    public function setMiddleware(array $route = [], array $before = [], array $after = []): Router
    {
        $this->globalMiddleware = [
            'before' => $before,
            'route' => $route,
            'after' => $after,
        ];
        return $this;
    }

    /**
     * Set options for route
     *
     * @param array $options
     * @return $this
     * @throws Exception
     */
    public function setOptions(array $options): Router
    {
        try {
            foreach ($options as $property => $value) {
                Settings::$$property = $value;
            }
            return $this;
        } catch (Error $e) {
            throw new Exception("'$property': Invalid setting or Type mismatch");
        }
    }

    /**
     * Set route pattern validation regex
     *
     * @param string $alias Alias (used in router)
     * @param string $regex regex to be validated with
     * @return $this
     * @throws Exception
     */
    public function setValidationRegex(string $alias, string $regex): Router
    {
        $alias = ':' . trim($alias);
        if (isset($this->pattern[$alias])) {
            throw new Exception('You can\'t override an existing rule!');
        }
        $this->pattern[$alias] = '(' . trim($regex, ' ()') . ')';
        $this->patternKeys = array_keys($this->pattern);
        return $this;
    }

    /**
     * Add route method by match
     *
     * @param $method
     * @param $params
     * @return bool
     * @throws Exception
     */
    public function __call($method, $params)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        if (empty($params) || count($params) !== 2) {
            return false;
        }
        return $this->match([strtoupper($method)], $params[0], $params[1]);
    }

    /**
     * Add route
     *
     * @param array $methods
     * @param string $route
     * @param array|callable $callback
     * @return bool|void
     * @throws Exception
     */
    public function match(array $methods, string $route, array|callable $callback)
    {
        if ($this->cacheLoaded) {
            return true;
        }
        if ($diff = array_diff($methods, $this->validMethods)) {
            throw new BadMethodCallException('Invalid method ' . implode(', ', $diff));
        }
        $pattern = $this->preparePattern($route);
        if (preg_match_all('/{(.*?)(:.*?)?}/', $pattern, $matches, PREG_SET_ORDER)) {
            if ((count($matches) * 4) !== count($matches, COUNT_RECURSIVE)) {
                throw new Exception("'$pattern' have typeless parameters!");
            }
            if ($unacceptable = array_diff(array_column($matches, 2), $this->patternKeys)) {
                throw new Exception("'$pattern' have unacceptable type(" . implode(', ', $unacceptable) . ")!");
            }
        }
        $this->buildRoute($methods, $pattern, $callback);
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
        $invoke = Invoke::instance();
        $invoke->middlewareGroup($this->globalMiddleware['before'] ?? []);
        $repository = Repository::instance();
        if ($repository->getStatus() === 200) {
            $this->handle();
        }
        $invoke->middlewareGroup($this->globalMiddleware['after'] ?? []);
        responseFlush();
        return $repository->getStatus();
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


    /**
     * Cache in depository (required if wanna use named route)
     *
     * @return $this
     */
    public function cacheInDepository(): static
    {
        $this->depository->setResource($this->getRoutes());
        return $this;
    }
}
