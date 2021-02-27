<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\WebFace\Utility\Arrject;
use AbmmHasan\WebFace\Utility\EndUser;
use AbmmHasan\WebFace\Utility\Headers;
use AbmmHasan\WebFace\Utility\URL;
use BadMethodCallException;

abstract class BaseRequest
{
    protected $post;
    protected $query;
    protected $server;
    protected $base;
    protected $client;
    protected $headers;
    protected $method;
    protected $originalMethod;
    protected $url;
    protected $xhr;
    protected $contentHeader;
    protected $accept;
    protected $dependencyHeader;
    protected $cookie;

    public function __construct()
    {
        $method = URL::getMethod();
        $this->server = new Arrject($_SERVER);
        $this->cookie = new Arrject($_COOKIE);
        $this->headers = Headers::all();
        $this->method = $method['converted'];
        $this->originalMethod = $method['main'];
        $this->contentHeader = Headers::content();
        $this->accept = Headers::accept();
        $this->url = URL::get();
        $this->dependencyHeader = Headers::responseDependency();
        $this->post = new Arrject($_POST);
        $this->query = new Arrject($_GET);
        $this->base = str_replace(['\\', ' '], ['/', '%20'], dirname($_SERVER['SCRIPT_NAME']));
        $this->client = EndUser::info();
        $this->xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }
}
