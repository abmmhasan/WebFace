<?php

namespace AbmmHasan\WebFace\Router\Asset;

use Closure;
use Exception;
use ReflectionException;
use function unserialize;

class Invoke
{
    /**
     * Invoke method
     *
     * @param array|Closure|string $fn
     * @param array $params
     * @throws ReflectionException|Exception
     */
    public static function method(array|Closure|string $fn, array $params = []): void
    {
        ob_start();
        match (true) {
            is_array($fn) =>
            container()
                ->registerMethod($fn[0], $fn[1], $params)
                ->callMethod($fn[0]),

            $fn instanceof Closure =>
            container()
                ->registerClosure('wf', $fn, $params)
                ->callClosure('wf'),

            is_string($fn) && (
                str_starts_with($fn, 'O:47:"Laravel\\SerializableClosure\\SerializableClosure') ||
                str_starts_with($fn, 'C:32:"Opis\\Closure\\SerializableClosure')
            ) =>
            container()
                ->registerClosure('wf', unserialize($fn)->getClosure(), $params)
                ->callClosure('wf'),

            default => throw new Exception('Unknown invoke formation!')
        };
        ob_end_clean();
    }

    /**
     * Invoke middleware
     *
     * @param $fn
     * @param string $params
     * @return mixed
     * @throws ReflectionException
     */
    public static function middleware($fn, string $params = ''): mixed
    {
        $params = array_filter(explode(',', $params));
        if ($fn instanceof Closure) {
            return container()->registerClosure('wf', $fn, $params)
                ->callClosure('wf');
        }
        return container()->registerMethod($fn, Settings::$middlewareCallMethod, $params)
            ->callMethod($fn);
    }

    /**
     * Execute middleware group
     *
     * @param array $resource
     * @return void
     * @throws ReflectionException
     */
    public static function middlewareGroup(array $resource): void
    {
        if (!empty($resource)) {
            foreach ($resource as $execute) {
                self::middleware($execute);
            }
        }
    }
}