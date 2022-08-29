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
    private CommonAsset $commonAsset;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->commonAsset = CommonAsset::instance();
        // Request assets
        $this->post = $this->commonAsset->post();
        $this->query = $this->commonAsset->query();
        $this->files = $this->commonAsset->files();
        $this->rawBody = $this->commonAsset->raw();
        $this->parsedBody = $this->commonAsset->parsedBody();
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
            'server' => $this->commonAsset->server(),
            'cookie' => $this->commonAsset->cookie(),
            'headers' => Headers::instance()->all(),
            'method' => URL::instance()->getMethod('converted'),
            'originalMethod' => URL::instance()->getMethod('main'),
            'contentHeader' => Headers::instance()->content(),
            'accept' => Headers::instance()->accept(),
            'url' => URL::instance()->get(),
            'dependencyHeader' => Headers::instance()->responseDependency(),
            'client' => EndUser::instance()->info(),
            'xhr' => URL::instance()->getMethod('isAjax'),
            default => throw new BadMethodCallException("Unknown asset: $name!")
        };
    }
}
