<?php


namespace AbmmHasan\WebFace\Router\Asset;


use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Response\Asset\ResponseDepot;
use Exception;
use ReflectionException;

abstract class BaseRoute
{
    protected array $routes = [];

    protected array $baseRoute = [];
    protected string $name = '';
    protected array $prefix = [];
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
        'XGET',
        'XPOST',
        'XPUT',
        'XDELETE',
        'XPATCH',
        'VIEW'
    ];
    public bool $cacheLoaded = false;
    protected array $pattern = [
        ':num' => '(-?[0-9]+)',
        ':alpha' => '([a-zA-Z]+)',
        ':alphanum' => '([a-zA-Z0-9]+)',
        ':any' => '([a-zA-Z0-9\.\-_%= \+\@\(\)]+)',
        ':all' => '(.*)',
    ];
    protected array $patternKeys;

    private array $keyMatch = [];

    /**
     * Base Route Constructor
     */
    public function __construct()
    {
        Settings::$basePath = URL::get('base');
        Settings::$cookieDomain = URL::get('host');
    }

    /**
     * Load route cache
     *
     * @return bool
     */
    protected function loadCache(): bool
    {
        if (file_exists($cachePath = Settings::$cachePath)) {
            $this->routes = require($cachePath);
            return true;
        }
        return false;
    }

    /**
     * Prepare route pattern (for further process)
     *
     * @param string $route
     * @return string
     */
    protected function preparePattern(string $route): string
    {
        $route = array_filter(explode('/', trim($route)));
        $route = array_merge($this->baseRoute, $route);
        return '/' . implode('/', $route);
    }

    /**
     * Prepare route asset (for further process)
     *
     * @param array $methods
     * @param string $pattern
     * @param array|callable $callback
     */
    protected function buildRoute(array $methods, string $pattern, array|callable $callback): void
    {
        $routeInfo = [
            'before' => $this->middleware['before'] ?? [],
            'after' => $this->middleware['after'] ?? [],
            'fn' => $callback
        ];
        $routeType = str_contains($pattern, '{') ? 'pattern' : 'plain';
        if (is_array($callback)) {
            if (!empty($callback['middleware']) || !empty($callback['before'])) {
                $before = array_unique(array_merge(
                    $routeInfo['before'], $callback['before'] ?? $callback['middleware']
                ));
                $routeInfo['before'] = $this->filterMiddlewareTag($before);
            }
            if (!empty($callback['after'])) {
                $after = array_unique(array_merge($routeInfo['after'], $callback['after']));
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
                    str_replace(array_merge(['{', '}'], $this->patternKeys), '', $pattern)
                )
            )
        );
        foreach ($methods as $method) {
            $this->routes['named'][$routeInfo['name']] ??= [$method, $pattern];
            $this->routes['list'][] = $pattern;
            $this->routes[$method][$routeType][$pattern] = $routeInfo;
        }
    }

    /**
     * Prepare grouped routes
     *
     * @param $settings
     */
    protected function prepareGroupContent($settings): void
    {
        if (!empty($settings['prefix'])) {
            $this->prefix = explode('/', trim($settings['prefix']));
            $this->baseRoute = array_filter(array_merge($this->baseRoute, $this->prefix));
        }
        if (!empty($settings['middleware']) || !empty($settings['before'])) {
            $this->middleware['before'] = array_unique(array_merge(
                $this->middleware['before'] ?? [], $settings['before'] ?? $settings['middleware']
            ));
        }
        if (!empty($settings['after'])) {
            $this->middleware['after'] = array_unique(array_merge(
                $this->middleware['after'] ?? [], $settings['after']
            ));
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
     * Find and execute exact route
     *
     * @param $routes
     * @param $method
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    protected function handle($routes, $method): bool
    {
        // Current Relative URL: remove rewrite base path from it (allows running the router in a subdirectory)
        $uri = '/' . trim(substr(URL::get('path'), strlen(Settings::$basePath)), '/');
        $toMatch = $method . ' ' . $uri;
        // absolute match
        if (isset($routes['plain'][$uri])) {
            RouteDepot::setCurrentRoute($toMatch);
            if (!$this->routeMiddlewareCheck($routes[$uri]['before'] ?? [], $this->globalMiddleware['route'])) {
                return true;
            }
            Invoke::method($routes[$uri]['fn']);
            Invoke::middlewareGroup($routes[$uri]['after'] ?? []);
            return true;
        }

        // pattern match
        foreach ($routes['pattern'] as $storedPattern => $route) {
            if (preg_match_all('#^' .
                preg_replace_callback('/{(.*?)(:.*?)?}/', [$this, 'prepareMatch'], $storedPattern) .
                '$#u', $uri, $matches, PREG_SET_ORDER)) {
                RouteDepot::setCurrentRoute($method . ' ' . $storedPattern, $toMatch);
                if (!$this->routeMiddlewareCheck($route['before'] ?? [], $this->globalMiddleware['route'])) {
                    return true;
                }
                unset($matches[0][0]);
                Invoke::method($route['fn'], array_combine($this->keyMatch, $matches[0]));
                Invoke::middlewareGroup($route['after'] ?? []);
                return true;
            }
            $this->keyMatch = [];
        }
        return false;
    }

    /**
     * Return matched pattern according to given identifier
     *
     * @param array $matches
     * @return string
     */
    private function prepareMatch(array $matches): mixed
    {
        $this->keyMatch[] = $matches[1];
        return $this->pattern[$matches[2]];
    }

    /**
     * Middleware tag filter (add/remove middleware based on given condition)
     *
     * @param array $array
     * @return array
     */
    private function filterMiddlewareTag(array $array): array
    {
        $result = [];
        if (!empty($array)) {
            foreach ($array as $item) {
                if (!empty($item) &&
                    !str_starts_with($item, '!') &&
                    !in_array($item, $result) &&
                    !in_array("!$item", $array)) {
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    /**
     * Checks route middleware for generating permission
     *
     * @param array $check
     * @param array $collection
     * @return bool
     * @throws Exception
     */
    private function routeMiddlewareCheck(array $check, array $collection): bool
    {
        if (empty($check) || empty($collection)) {
            return true;
        }
        foreach ($check as $middleware) {
            $parameterSeparation = explode(':', $middleware, 2);
            if (!isset($collection[$parameterSeparation[0]])) {
                throw new Exception("Unknown middleware alias: '$parameterSeparation[0]'");
            }
            $eligible = Invoke::middleware($collection[$parameterSeparation[0]], $parameterSeparation[1] ?? '');
            if ($eligible !== true) {
                ResponseDepot::setStatus($eligible['status'] ?? 403);
                ResponseDepot::setContent([
                    'status' => 'failed',
                    'message' => $eligible['message'] ?? (is_string($eligible) ? $eligible : 'Bad Request')
                ]);
                return false;
            }
        }
        return true;
    }
}
