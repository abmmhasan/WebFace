<?php

namespace AbmmHasan\WebFace\Router\Asset;

use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\Uuid;
use Exception;

class Depository
{
    use Single;

    protected array $signature = [];
    protected array $currentRoute = [];
    protected array $resource = [];

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->signature = [
            'uuid' => Uuid::v1(Settings::$node)
        ];
    }

    /**
     * Set route resource
     *
     * @param array $resource
     * @return void
     */
    public function setResource(array $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * Get route resource
     *
     * @param string|null $key
     */
    public function getResource(string $key = null): mixed
    {
        if ($key === null) {
            return $this->resource;
        }
        return $this->resource[$key] ?? null;
    }

    /**
     * Set active route
     *
     * @param string $pattern
     * @param string $uri
     * @param string $method
     */
    public function setRoute(
        string $pattern,
        string $uri,
        string $method
    ): void
    {
        $this->currentRoute = [
            'pattern' => $pattern,
            'method' => $method,
            'uri' => $uri
        ];
    }

    /**
     * Get active route
     *
     * @param string|null $key
     */
    public function getRoute(string $key = null): mixed
    {
        if ($key === null) {
            return $this->currentRoute;
        }
        return $this->currentRoute[$key] ?? null;
    }

    /**
     * Get active route signature
     *
     * @param string $key
     * @return mixed
     * @throws Exception
     */
    public function getSignature(string $key): mixed
    {
        return match ($key) {
            'uuid' => $this->signature['uuid'],
            'pattern' => $this->signature['pattern'] ??= $this->buildSignature($this->currentRoute['method'], $this->currentRoute['pattern']),
            'uri' => $this->signature['uri'] ??= $this->buildSignature($this->currentRoute['method'], $this->currentRoute['uri']),
            default => throw new Exception("Unknown signature key $key")
        };
    }

    /**
     * Build signature for a specific route
     *
     * @param string $method
     * @param string $uri
     * @return int
     */
    public function buildSignature(string $method, string $uri): int
    {
        return crc32("$method-$uri");
    }
}
