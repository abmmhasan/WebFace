<?php

use AbmmHasan\WebFace\Base\BaseResponse;
use AbmmHasan\WebFace\Middleware\PreTag;
use AbmmHasan\WebFace\Support\RouteDepot;
use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Router;
use AbmmHasan\WebFace\Utility\URL;

if (!function_exists('responseFlush')) {
    /**
     * Send response
     * @throws Exception
     */
    function responseFlush()
    {
        (new BaseResponse)->helloWorld();
    }
}

if (!function_exists('projectPath')) {
    /**
     * Get current project path
     */
    function projectPath(): string
    {
        $resolve = php_sapi_name() === 'cli' ? './' : '..';
        return realpath($resolve) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('setPreTag')) {
    /**
     * Get current project path
     * @param $path
     * @param $tag
     * @return bool|int
     * @throws Exception
     */
    function setPreTag($path, $tag): bool|int
    {
        return (new PreTag())->set($path, $tag);
    }
}

if (!function_exists('webFace')) {
    /**
     * Initiate router
     *
     * @param array $middlewareList
     * @param bool $loadCache
     * @throws Exception
     */
    function webFace(array $middlewareList = [], bool $loadCache = true)
    {
        $router = new Router($middlewareList, $loadCache);
        if (!$router->cacheLoaded) {
            $loadFrom = projectPath() . Settings::$resource_path;
            foreach (glob($loadFrom . '*.php') as $filename) {
                require_once($filename);
            }
        }
        RouteDepot::$cached_route_resource = $router->getRoutes();
        $router->run();
    }
}

if (!function_exists('route')) {
    /**
     * Resolve route path from name
     *
     * @param string $name
     * @param array|object|null $params
     * @param int $encoding
     * @return array|null
     */
    function route(string $name, array|object $params = null, int $encoding = PHP_QUERY_RFC3986): ?array
    {
        $namedRoutes = RouteDepot::getResource('named');
        if (isset($namedRoutes[$name])) {
            $url = URL::get('prefix') . trim($namedRoutes[$name][1], '/');
            if (!empty($params)) {
                $url .= '?' . http_build_query($params, $encoding);
            }
            return [
                $namedRoutes[$name][0],
                $url
            ];
        }
        return null;
    }
}

if (!function_exists('httpDate')) {
    /**
     * Converts any recognizable date format to an HTTP date.
     *
     * @param mixed|null $date The incoming date value.
     * @return string A formatted date.
     * @throws Exception
     */
    function httpDate(mixed $date = null): string
    {
        if ($date instanceof DateTime) {
            $date = DateTimeImmutable::createFromMutable($date);
        } else {
            $date = new DateTime($date);
        }

        try {
            $date->setTimeZone(new DateTimeZone('UTC'));
        } catch (\Exception $e) {
            $date = new DateTime('0001-01-01', new DateTimeZone('UTC'));
        } finally {
            return $date->format('D, d M Y H:i:s') . ' GMT';
        }
    }
}
