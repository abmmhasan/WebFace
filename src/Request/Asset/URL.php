<?php


namespace AbmmHasan\WebFace\Request\Asset;


use AbmmHasan\Bucket\Functional\Arrject;
use AbmmHasan\OOF\Fence\Single;
use Exception;

final class URL
{
    private Arrject $url;
    private Arrject $method;
    private array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];

    use Value, Single;

    /**
     * Get current URL (parsed)
     *
     * @param string|null $key
     * @return mixed
     * @throws Exception
     */
    public function get(string $key = null): mixed
    {
        if (!isset($this->url)) {
            $commonAsset = CommonAsset::instance();
            $host = $commonAsset->server('HTTP_HOST') ?? 'localhost';
            $port = $commonAsset->server('SERVER_PORT');
            if (is_null($port)) {
                preg_match('/:[0-9]+$/m', $host, $matches);
                if ($matches) {
                    $port = array_pop($matches);
                }
            }
            $request_uri = $commonAsset->server('REQUEST_URI');
            $scheme = $this->getScheme();
            $full_url = $scheme . $host . ':' . $port . $request_uri;
            $parts = parse_url($full_url);
            if ($commonAsset->server('HTTP_HOST') === null && $commonAsset->server('SERVER_NAME') === null) {
                $parts[PHP_URL_HOST] = null;
            }
            $this->url = new Arrject([
                    'url' => $full_url,
                    'base' => $base = strtr(dirname($commonAsset->server('SCRIPT_NAME')), [
                        '\\' => '/',
                        ' ' => '%20'
                    ]),
                    'prefix' => $scheme . $host . ':' . $port . $base,
                ] + $parts);
        }
        return $this->find($this->url, $key);
    }

    /**
     * Get request method
     *
     * @param string|null $key
     * @return mixed
     * @throws Exception
     */
    public function getMethod(string $key = null): mixed
    {
        if (!isset($this->method)) {
            $commonAsset = CommonAsset::instance();
            $originalMethod = $requestMethod = strtoupper($commonAsset->server('REQUEST_METHOD') ?? 'GET');
            if (!in_array($requestMethod, $this->methods)) {
                throw new Exception("Invalid method override {$requestMethod}.");
            }
            if ($requestMethod === 'HEAD') {
                $requestMethod = 'GET';
            } elseif ($requestMethod === 'POST') {
                $headers = Headers::instance()->all();
                $requestMethod = $headers['X-HTTP-Method-Override']
                    ?? $headers['HTTP-Method-Override']
                    ?? $commonAsset->post('_method')
                    ?? $requestMethod;
            }
            $this->method = new Arrject([
                'main' => $originalMethod,
                'converted' => strtoupper($requestMethod),
                'isAjax' => $commonAsset->server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'
            ]);
        }
        return $this->find($this->method, $key);
    }

    /**
     * Get scheme
     *
     * @return string
     * @throws Exception
     */
    private function getScheme(): string
    {
        $server = CommonAsset::instance()->server();
        return ((isset($server['HTTPS']) && strtolower($server['HTTPS']) == 'on')
            || (isset($server['REQUEST_SCHEME']) && $server['REQUEST_SCHEME'] === 'https')
            || (isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($server['HTTP_FRONT_END_HTTPS']) && $server['HTTP_FRONT_END_HTTPS'] === 'on')
            || (isset($server['HTTP_X_FORWARDED_PROTO']) && strtolower($server['HTTP_X_FORWARDED_PROTO']) == 'https')
            || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == 443))
            ? 'https://' : 'http://';
    }
}
