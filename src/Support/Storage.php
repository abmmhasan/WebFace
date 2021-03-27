<?php


namespace AbmmHasan\WebFace\Support;


use AbmmHasan\WebFace\Router;

class Storage
{
    public static $response_throw = [];
    private static $route_in_operation = '/';
    private static $route_resource;

    public static function cache()
    {
        self::loadRoute();
        $content = self::removeClosures(self::$route_resource);
        return file_put_contents(
            projectPath() . Settings::$cache_path,
            '<?php return ' . var_export($content, true) . ';' . PHP_EOL,
            LOCK_EX
        );
    }

    public static function getRouteResource($key)
    {
        self::loadRoute();
        if (isset(self::$route_resource[$key])) {
            return $key;
        }
        return null;
    }

    private static function loadRoute()
    {
        if (!isset(self::$route_resource)) {
            $router = new Router();
            foreach (glob(projectPath() . Settings::$resource_path . '*.php') as $filename) {
                require_once($filename);
            }
            self::$route_resource = $router->getRoutes();
        }
    }

    public static function setCurrentRoute($route = null)
    {
        self::$route_in_operation = $route;
    }

    public static function getCurrentRoute()
    {
        return self::$route_in_operation;
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