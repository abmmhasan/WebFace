<?php

use AbmmHasan\WebFace\Middleware\PreTag;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Response\Asset\Dispatch;
use AbmmHasan\WebFace\Response\Response as ResponseAlias;
use AbmmHasan\WebFace\Router\Asset\RouteDepot;
use AbmmHasan\WebFace\Router\Asset\Settings;
use AbmmHasan\WebFace\Router\Router;

if (!function_exists('responseFlush')) {
    /**
     * Send response
     *
     * @throws Exception
     */
    function responseFlush(): void
    {
        (new Dispatch())->hello();
    }
}

if (!function_exists('response')) {
    /**
     * Get response instance
     *
     * @throws Exception
     */
    function response(string|array $content = null, int $status = 200, array $headers = []): ResponseAlias
    {
        return ResponseAlias::instance($content, $status, $headers);
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
     * @param array $settings
     * @return Router
     * @throws Exception
     */
    function webFace(array $settings): Router
    {
        $router = new Router();
        $router->setOptions($settings);
        if (!$router->cacheLoaded) {
            foreach (glob(Settings::$resourcePath . '*.php') as $filename) {
                require_once($filename);
            }
        }
        RouteDepot::setResource($router->getRoutes());
        return $router;
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
            $url = URL::instance()->get('prefix') . trim($namedRoutes[$name][1], '/');
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
