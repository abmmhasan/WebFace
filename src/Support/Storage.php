<?php


namespace AbmmHasan\WebFace\Support;


use AbmmHasan\WebFace\Router;

class Storage
{
    public static $response_throw = [];

    public static function cacheRoute(string $route_dir)
    {
        $router = new Router();
        foreach (glob($route_dir . '*.php') as $filename) {
            require_once($filename);
        }
        $content = self::removeClosures($router->getRoutes());
        file_put_contents(
            Settings::$cache_path,
            '<?php return ' . var_export($content, true) . ';' . PHP_EOL,
            LOCK_EX
        );
    }

    private static function removeClosures($content)
    {
        $filtered = [];
        foreach ($content as $method => $routeList) {
            foreach ($routeList as $pattern => $route) {
                if (is_string($route['fn'])) {
                    $filtered[$method][$pattern] = $route;
                }
            }
        }
        return $filtered;
    }
}