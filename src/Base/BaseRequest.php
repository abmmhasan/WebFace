<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\WebFace\Utility\Arrject;
use AbmmHasan\WebFace\Utility\EndUser;
use AbmmHasan\WebFace\Utility\Headers;
use AbmmHasan\WebFace\Utility\RequestAsset;
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
    protected $request;
    protected $files;

    public function __construct()
    {
        $method = URL::getMethod();
        $this->server = RequestAsset::server();
        $this->cookie = RequestAsset::cookie();
        $this->headers = Headers::all();
        $this->method = URL::getMethod('converted');
        $this->originalMethod = URL::getMethod('main');
        $this->contentHeader = Headers::content();
        $this->accept = Headers::accept();
        $this->url = URL::get();
        $this->dependencyHeader = Headers::responseDependency();
        $this->post = RequestAsset::post();
        $this->query = RequestAsset::query();
        $this->files = RequestAsset::files();
        $this->request = new Arrject(self::getRequest());
        $this->base = str_replace(['\\', ' '], ['/', '%20'], dirname($_SERVER['SCRIPT_NAME']));
        $this->client = EndUser::info();
        $this->xhr = URL::getMethod('isAjax');
    }

    private function getRequest()
    {
        $data = $this->post->toArray() + $this->files->toArray() + $this->query->toArray();
        if ($input = file_get_contents('php://input')) {
            switch ($this->contentHeader->type) {
                case 'application/json':
                    $data += json_decode($input, true);
                    break;
                case 'application/xml':
                    $input = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $input);
                    $input = preg_replace('/\s\s+/', " ", $input);
                    $input = simplexml_load_string($input);
                    $data += json_decode(json_encode($input), true);
                    break;
                default:
                    break;
            }
        }
        return $data;
    }
}
