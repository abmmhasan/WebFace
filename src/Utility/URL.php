<?php


namespace AbmmHasan\WebFace\Utility;


final class URL
{
    private static $url;
    private static $method;
    private static $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'];

    /**
     * Get current URL (parsed)
     *
     * @return Arrject
     */
    public static function get()
    {
        if (self::$url) {
            return self::$url;
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $port = $_SERVER['SERVER_PORT'] ?? null;
        if (is_null($port)) {
            preg_match('/:[0-9]+$/m', $host, $matches);
            if ($matches) {
                $port = array_pop($matches);
            }
        }
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $base_path = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
        $full_url = self::getScheme() . $host . ':' . $port . $request_uri;
        $parts = parse_url($full_url);
        if (!isset($_SERVER['HTTP_HOST']) && !isset($_SERVER['SERVER_NAME'])) {
            $parts[PHP_URL_HOST] = null;
        }
        return self::$url = new Arrject(['url' => $full_url, 'base' => $base_path] + $parts);
    }

    public static function getMethod()
    {
        if (self::$method) {
            return self::$method;
        }
        $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
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
            } elseif (isset($_POST['_method'])) {
                $requestMethod = $_POST['_method'];
            }
        }
        return self::$method = new Arrject([
            'main' => strtoupper($originalMethod),
            'converted' => strtoupper($requestMethod)
        ]);
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