<?php


namespace AbmmHasan\WebFace\Utility;



final class URL
{
    private static $url;

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