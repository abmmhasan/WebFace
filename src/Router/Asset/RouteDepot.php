<?php


namespace AbmmHasan\WebFace\Router\Asset;


use AbmmHasan\WebFace\Router\Router;
use Closure;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;

final class RouteDepot
{
    private static string $routeInOperation = '/';
    private static mixed $routeResource;
    private static array $cachedRouteResource;

    /**
     * Prepare and cache route
     *
     * @return bool|int
     * @throws PhpVersionNotSupportedException
     */
    public static function cache(): bool|int
    {
        self::prepareRoute();
        $content = self::parseResource(self::$routeResource);
        return file_put_contents(
            Settings::$cachePath,
            '<?php return ' . var_export($content, true) . ';' . PHP_EOL,
            LOCK_EX
        );
    }

    /**
     * Get route resource
     *
     * @param $key
     * @return mixed
     */
    public static function getResource($key): mixed
    {
        return self::$cachedRouteResource[$key] ?? null;
    }

    /**
     * Get route resource
     *
     * @param array $resource
     */
    public static function setResource(array $resource): void
    {
        self::$cachedRouteResource = $resource;
    }

    /**
     * Prepare route resources
     */
    private static function prepareRoute(): void
    {
        if (!isset(self::$routeResource)) {
            $router = new Router();
            foreach (glob(Settings::$resourcePath . '*.php') as $filename) {
                require_once($filename);
            }
            self::$routeResource = $router->getRoutes();
        }
    }

    /**
     * Set active route
     *
     * @param string|null $route
     */
    public static function setCurrentRoute(string $route = null): void
    {
        self::$routeInOperation = $route;
    }

    /**
     * Get active route
     *
     * @return string
     */
    public static function getCurrentRoute(): string
    {
        return self::$routeInOperation;
    }

    /**
     * Removing closure as it is not supported in export
     *
     * @param $content
     * @return array
     * @throws PhpVersionNotSupportedException
     */
    private static function parseResource($content): array
    {
        $filtered = [];
        foreach ($content as $method => $routeList) {
            if (in_array($method, ['named', 'list'])) {
                $filtered[$method] = $routeList;
                continue;
            }
            foreach ($routeList as $pattern => $route) {
                if ($route['fn'] instanceof Closure) {
                    $route['fn'] = serialize(new SerializableClosure($route['fn']));
                    $filtered[$method][$pattern] = $route;
                } elseif (is_string($route['fn'])) {
                    $filtered[$method][$pattern] = $route;
                }
            }
        }
        return $filtered;
    }
}
