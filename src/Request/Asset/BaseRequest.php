<?php

namespace AbmmHasan\WebFace\Request\Asset;

use AbmmHasan\Bucket\Functional\Arrject;
use BadMethodCallException;
use Exception;

abstract class BaseRequest
{
    protected Arrject $post;
    protected Arrject $query;
    protected Arrject $files;
    protected string|bool $rawBody;
    protected mixed $parsedBody;
    protected Arrject $request;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        // Request assets
        $this->post = CommonAsset::post();
        $this->query = CommonAsset::query();
        $this->files = CommonAsset::files();
        $this->rawBody = CommonAsset::raw();
        $this->parsedBody = CommonAsset::parsedBody();
        $this->request ??= new Arrject($this->getRequest());
    }

    /**
     * Get Request in prioritized order
     * https://specs.openstack.org/openstack/api-wg/guidelines/http/methods.html
     *
     * @return array
     * @throws Exception
     */
    protected function getRequest(): array
    {
        return in_array($this->getAsset('method'), ['GET', 'DELETE'])
            ? $this->query->toArray()
            : (
                (!$this->parsedBody ? [] : $this->parsedBody->toArray()) +
                $this->post->toArray() +
                $this->files->toArray() +
                $this->query->toArray()
            );
    }

    /**
     * Get request asset
     *
     * @param $name
     * @return mixed
     * @throws Exception
     */
    protected function getAsset($name): mixed
    {
        return match ($name) {
            'post' => $this->post,
            'query' => $this->query,
            'files' => $this->files,
            'rawBody' => $this->rawBody,
            'parsedBody' => $this->parsedBody,
            'server' => CommonAsset::server(),
            'cookie' => CommonAsset::cookie(),
            'headers' => Headers::all(),
            'method' => URL::getMethod('converted'),
            'originalMethod' => URL::getMethod('main'),
            'contentHeader' => Headers::content(),
            'accept' => Headers::accept(),
            'url' => URL::get(),
            'dependencyHeader' => Headers::responseDependency(),
            'client' => EndUser::info(),
            'xhr' => URL::getMethod('isAjax'),
            default => throw new BadMethodCallException("Unknown asset: $name!")
        };
    }
}
