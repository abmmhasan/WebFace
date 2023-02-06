<?php

namespace AbmmHasan\WebFace\Router\Asset;

use AbmmHasan\OOF\Exceptions\ContainerException;
use AbmmHasan\OOF\Exceptions\NotFoundException;
use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\WebFace\Response\Asset\HTTPResource;
use Closure;
use Exception;

use http\Exception\InvalidArgumentException;
use ReflectionException;

use function unserialize;

final class Invoke
{
    use Single;

    /**
     * Invoke method
     *
     * @param array|Closure|string $fn
     * @param array $params
     * @return int|null
     * @throws ContainerException|NotFoundException|ReflectionException|Exception
     */
    public function method(array|Closure|string $fn, array $params = []): ?int
    {
        ob_start();
        $status = match (true) {
            is_array($fn) =>
            container()
                ->registerMethod($fn[0], $fn[1], $params)
                ->get($fn[0]),

            $fn instanceof Closure =>
            container()
                ->registerClosure('wf', $fn, $params)
                ->get('wf'),

            is_string($fn) => match (true) {
                is_callable($fn, false, $callableName) =>
                container()
                    ->registerClosure($callableName, $fn, $params)
                    ->get($callableName),

                str_starts_with($fn, 'C:32:"Opis\\Closure\\SerializableClosure') =>
                container()
                    ->registerClosure('wf', unserialize($fn)->getClosure(), $params)
                    ->get('wf')
                    ->returned,

                default => throw new InvalidArgumentException('Unknown invoke formation!')
            },

            default => throw new InvalidArgumentException('Unknown invoke formation!')
        };
        ob_end_clean();
        if (!empty($status)) {
            $status = intval($status);
            if (isset(HTTPResource::$statusList[$status])) {
                return $status;
            }
        }
        return null;
    }

    /**
     * Invoke middleware
     *
     * @param $fn
     * @param string $params
     * @return mixed
     * @throws Exception
     */
    public function middleware($fn, string $params = ''): mixed
    {
        $params = array_filter(explode(',', $params));
        if ($fn instanceof Closure) {
            $signature = trim(base64_encode(random_bytes(5)), '=');
            return container()
                ->registerClosure($signature, $fn, $params)
                ->call($signature)
                ->returned;
        }
        return container()
            ->registerMethod($fn, Settings::$middlewareCallMethod, $params)
            ->call($fn)
            ->returned;
    }

    /**
     * Execute middleware group
     *
     * @param array $resource
     * @param string $params
     * @return void
     * @throws Exception
     */
    public function middlewareGroup(array $resource, string $params = ''): void
    {
        if ($resource !== []) {
            foreach ($resource as $execute) {
                $this->middleware($execute, $params);
            }
        }
    }
}
