<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\WebFace\Utility\Arrject;
use AbmmHasan\WebFace\Utility\ClientIP;
use AbmmHasan\WebFace\Utility\Headers;
use AbmmHasan\WebFace\Utility\URL;
use BadMethodCallException;

class BaseRequest
{
    protected $post;
    protected $query;
    protected $server;
    protected $base;
    protected $client;
    protected $headers;
    protected $method;
    protected $url;
    protected $xhr;
    protected $contentHeader;
    private $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];
    protected $accept;
    protected $dependencyHeader;
    protected $cookie;
    protected $thrownResponse;

    public function __construct()
    {
        $this->server = new Arrject($_SERVER);
        $this->cookie = new Arrject($_COOKIE);
        $this->headers = Headers::all();
        $this->method = self::getMethod();
        $this->contentHeader = Headers::content();
        $this->accept = Headers::accept();
        $this->url = URL::get();
        $this->dependencyHeader = Headers::responseDependency();
        $this->post = new Arrject($_POST);
        $this->query = new Arrject($_GET);
        $this->base = str_replace(['\\', ' '], ['/', '%20'], dirname($_SERVER['SCRIPT_NAME']));
        $this->client = new Arrject($this->clientInfo());
        $this->xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    private function getMethod()
    {
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD']) ?? 'GET';
        if (!in_array($requestMethod, $this->methods)) {
            throw new BadMethodCallException("Invalid method override {$requestMethod}.");
        }
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
            ob_start();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($this->headers['X-HTTP-Method-Override'])) {
                $requestMethod = $this->headers['X-HTTP-Method-Override'];
            } elseif (isset($this->post['_method'])) {
                $requestMethod = $this->post['_method'];
            }
        }
        return strtoupper($requestMethod);
    }

    private function clientInfo()
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'proxy_ip' => ClientIP::get(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'ua' => [
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'system' => $this->userAgentInfo(),
            ]
        ];
    }

    private function userAgentInfo()
    {
        if (ini_get('browscap')) {
            return get_browser(null, true);
        }
        return [];
    }
}
