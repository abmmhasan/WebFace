<?php


namespace AbmmHasan\WebFace\Router\Asset;


use AbmmHasan\WebFace\Router\Router;

final class RouteDepot
{
    private static string $route_in_operation = '/';
    private static mixed $route_resource;
    public static array $cached_route_resource;

    /**
     * Prepare and cache route
     *
     * @return bool|int
     */
    public static function cache(): bool|int
    {
        self::prepareRoute();
        $content = self::removeClosures(self::$route_resource);
        return file_put_contents(
            projectPath() . Settings::$cachePath,
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
        return self::$cached_route_resource[$key] ?? null;
    }

    /**
     * Prepare route resources
     */
    private static function prepareRoute()
    {
        if (!isset(self::$route_resource)) {
            $router = new Router([], false);
            foreach (glob(projectPath() . Settings::$resourcePath . '*.php') as $filename) {
                require_once($filename);
            }
            self::$route_resource = $router->getRoutes();
        }
    }

    /**
     * Set active route
     *
     * @param string|null $route
     */
    public static function setCurrentRoute(string $route = null)
    {
        self::$route_in_operation = $route;
    }

    /**
     * Get active route
     *
     * @return string
     */
    public static function getCurrentRoute(): string
    {
        return self::$route_in_operation;
    }

    /**
     * Removing closure as it is not supported in export
     *
     * @param $content
     * @return array
     */
    private static function removeClosures($content): array
    {
        $filtered = [];
        foreach ($content as $method => $routeList) {
            if (in_array($method, ['named', 'list'])) {
                $filtered[$method] = $routeList;
                continue;
            }
            foreach ($routeList as $pattern => $route) {
                if (is_string($route['fn'])) {
                    $filtered[$method][$pattern] = $route;
                }
            }
        }
        return $filtered;
    }
}
