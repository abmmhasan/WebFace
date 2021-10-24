<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\OOF\Remix\Arrject;
use AbmmHasan\WebFace\Utility\EndUser;
use AbmmHasan\WebFace\Utility\Headers;
use AbmmHasan\WebFace\Utility\RequestAsset;
use AbmmHasan\WebFace\Utility\URL;

abstract class BaseRequest
{
    protected Arrject $post;
    protected Arrject $query;
    protected Arrject $server;
    protected Arrject $client;
    protected Arrject $headers;
    protected string $method;
    protected string $originalMethod;
    protected Arrject $url;
    protected bool $xhr;
    protected Arrject $contentHeader;
    protected Arrject $accept;
    protected Arrject $dependencyHeader;
    protected Arrject $cookie;
    protected Arrject $request;
    protected Arrject $files;
    private string|bool $rawBody;
    private mixed $parsedBody;

    public function __construct()
    {
        $this->server = RequestAsset::server();
        $this->cookie = RequestAsset::cookie();
        $this->headers = Headers::all();
        $this->method = URL::getMethod('converted');
        $this->originalMethod = URL::getMethod('main');
        $this->contentHeader = Headers::content();
        $this->accept = Headers::accept();
        $this->url = URL::get();
        $this->dependencyHeader = Headers::responseDependency();
        $this->client = EndUser::info();
        $this->xhr = URL::getMethod('isAjax');

        // Request assets
        $this->post = RequestAsset::post();
        $this->query = RequestAsset::query();
        $this->files = RequestAsset::files();
        $this->rawBody = RequestAsset::raw();
        $this->parsedBody = RequestAsset::parsedBody();
        $this->request ??= new Arrject(self::getRequest());
    }

    /**
     * Get Request in prioritized order
     *
     * @return array
     */
    private function getRequest(): array
    {
        return $this->parsedBody->toArray() + $this->post->toArray() + $this->files->toArray() + $this->query->toArray();
    }
}
