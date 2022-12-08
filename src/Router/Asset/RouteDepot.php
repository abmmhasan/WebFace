<?php


namespace AbmmHasan\WebFace\Router\Asset;


use AbmmHasan\WebFace\Router\Router;
use Closure;
use Opis\Closure\SerializableClosure;

final class RouteDepot
{
    private static mixed $routeResource;

    /**
     * Prepare and cache route
     *
     * @return bool|int
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
     * Resource parser
     *
     * @param $content
     * @return array
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
