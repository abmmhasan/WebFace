<?php


namespace AbmmHasan\WebFace\Utility;


final class URL extends Utility
{
    private static $url;
    private static $method;
    private static $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];

    /**
     * Get current URL (parsed)
     *
     * @return Arrject
     */
    public static function get($key = null)
    {
        if (!isset(self::$url)) {
            $host = RequestAsset::server('HTTP_HOST') ?? 'localhost';
            $port = RequestAsset::server('SERVER_PORT');
            if (is_null($port)) {
                preg_match('/:[0-9]+$/m', $host, $matches);
                if ($matches) {
                    $port = array_pop($matches);
                }
            }
            $request_uri = RequestAsset::server('REQUEST_URI');
            $base_path = implode('/', array_slice(explode('/', RequestAsset::server('SCRIPT_NAME')), 0, -1)) . '/';
            $full_url = self::getScheme() . $host . ':' . $port . $request_uri;
            $parts = parse_url($full_url);
            if (is_null(RequestAsset::server('HTTP_HOST')) && is_null(RequestAsset::server('SERVER_NAME'))) {
                $parts[PHP_URL_HOST] = null;
            }
            self::$url = new Arrject(['url' => $full_url, 'base' => $base_path] + $parts);
        }
        return self::getValue(self::$url, $key);
    }

    public static function getMethod($key = null)
    {
        if (!isset(self::$method)) {
            $requestMethod = strtoupper(RequestAsset::server('REQUEST_METHOD') ?? 'GET');
            if (!in_array($requestMethod, self::$methods)) {
                throw new BadMethodCallException("Invalid method override {$requestMethod}.");
            }
            $originalMethod = $requestMethod;
            if ($requestMethod === 'HEAD') {
                $requestMethod = 'GET';
            } elseif ($requestMethod === 'POST') {
                $headers = Headers::all();
                if (isset($headers['X-HTTP-Method-Override'])) {
                    $requestMethod = $headers['X-HTTP-Method-Override'];
                } elseif (!is_null($method = RequestAsset::post('_method'))) {
                    $requestMethod = $method;
                }
            }
            self::$method = new Arrject([
                'main' => strtoupper($originalMethod),
                'converted' => strtoupper($requestMethod),
                'isAjax' => RequestAsset::server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'
            ]);
        }
        return self::getValue(self::$method, $key);
    }

    private static function getScheme()
    {
        return ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
            || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && $_SERVER['HTTP_FRONT_END_HTTPS'] === 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(
                    $_SERVER['HTTP_X_FORWARDED_PROTO']
                ) == 'https')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443))
            ? 'https://' : 'http://';
    }
}