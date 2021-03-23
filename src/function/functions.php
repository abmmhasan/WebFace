<?php

use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Router;

if (!function_exists('responseFlush')) {
    /**
     * Send response
     */
    function responseFlush()
    {
        AbmmHasan\WebFace\Response::instance()->send();
    }
}

if (!function_exists('projectPath')) {
    /**
     * Get current project path
     */
    function projectPath()
    {
        $resolve = php_sapi_name() === 'cli' ? './' : '..';
        return realpath($resolve) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('webface')) {
    /**
     * Initiate router
     *
     * @param array $middlewareList
     * @param bool $loadCache
     */
    function webface($middlewareList = [], $loadCache = true)
    {
        $router = new Router($middlewareList, $loadCache);
        if (!$router->cacheLoaded) {
            $loadFrom = projectPath() . Settings::$resource_path;
            foreach (glob($loadFrom . '*.php') as $filename) {
                require_once($filename);
            }
        }
        $router->run();
    }
}

if (!function_exists('route')) {
    /**
     * Initiate router
     *
     * @param array $middlewareList
     * @param bool $loadCache
     */
    function route($path)
    {

    }
}

if (!function_exists('httpDate')) {
    /**
     * Converts any recognizable date format to an HTTP date.
     *
     * @param mixed $date The incoming date value.
     * @return string A formatted date.
     * @throws Exception
     */
    function httpDate($date = null)
    {
        if ($date instanceof \DateTime) {
            $date = \DateTimeImmutable::createFromMutable($date);
        } else {
            $date = new \DateTime($date);
        }

        try {
            $date->setTimeZone(new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            $date = new \DateTime('0001-01-01', new \DateTimeZone('UTC'));
        } finally {
            return $date->format('D, d M Y H:i:s') . ' GMT';
        }
    }
}