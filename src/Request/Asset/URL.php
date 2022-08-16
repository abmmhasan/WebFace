<?php


namespace AbmmHasan\WebFace\Request\Asset;


use AbmmHasan\Bucket\Functional\Arrject;
use Exception;

final class URL extends Utility
{
    private static Arrject $url;
    private static Arrject $method;
    private static array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];

    /**
     * Get current URL (parsed)
     *
     * @param string|null $key
     * @return mixed
     */
    public static function get(string $key = null): mixed
    {
        if (!isset(self::$url)) {
            $host = CommonAsset::server('HTTP_HOST') ?? 'localhost';
            $port = CommonAsset::server('SERVER_PORT');
            if (is_null($port)) {
                preg_match('/:[0-9]+$/m', $host, $matches);
                if ($matches) {
                    $port = array_pop($matches);
                }
            }
            $request_uri = CommonAsset::server('REQUEST_URI');
            $scheme = self::getScheme();
            $full_url = $scheme . $host . ':' . $port . $request_uri;
            $parts = parse_url($full_url);
            if (CommonAsset::server('HTTP_HOST') === null && CommonAsset::server('SERVER_NAME') === null) {
                $parts[PHP_URL_HOST] = null;
            }
            self::$url = new Arrject([
                    'url' => $full_url,
                    'base' => $base = str_replace(['\\', ' '], ['/', '%20'], dirname(CommonAsset::server('SCRIPT_NAME'))),
                    'prefix' => $scheme . $host . ':' . $port . $base,
                ] + $parts);
        }
        return self::getValue(self::$url, $key);
    }

    /**
     * Get request method
     *
     * @param string|null $key
     * @return mixed
     * @throws Exception
     */
    public static function getMethod(string $key = null): mixed
    {
        if (!isset(self::$method)) {
            $originalMethod = $requestMethod = strtoupper(CommonAsset::server('REQUEST_METHOD') ?? 'GET');
            if (!in_array($requestMethod, self::$methods)) {
                throw new Exception("Invalid method override {$requestMethod}.");
            }
            if ($requestMethod === 'HEAD') {
                $requestMethod = 'GET';
            } elseif ($requestMethod === 'POST') {
                $headers = Headers::all();
                $requestMethod = $headers['X-HTTP-Method-Override']
                    ?? $headers['HTTP-Method-Override']
                    ?? CommonAsset::post('_method')
                    ?? $requestMethod;
            }
            self::$method = new Arrject([
                'main' => $originalMethod,
                'converted' => strtoupper($requestMethod),
                'isAjax' => CommonAsset::server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'
            ]);
        }
        return self::getValue(self::$method, $key);
    }

    private static function getScheme(): string
    {
        $server = CommonAsset::server();
        return ((isset($server['HTTPS']) && strtolower($server['HTTPS']) == 'on')
            || (isset($server['REQUEST_SCHEME']) && $server['REQUEST_SCHEME'] === 'https')
            || (isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($server['HTTP_FRONT_END_HTTPS']) && $server['HTTP_FRONT_END_HTTPS'] === 'on')
            || (isset($server['HTTP_X_FORWARDED_PROTO']) && strtolower($server['HTTP_X_FORWARDED_PROTO']) == 'https')
            || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == 443))
            ? 'https://' : 'http://';
    }
}
