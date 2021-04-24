<?php


namespace AbmmHasan\WebFace\Support;


class ResponseDepot
{
    public static $code = 200;
    public static $contentType = 'json';
    public static $content = '';
    public static $charset = 'UTF-8';

    private static $header;
    private static $cacheHeader;
    private static $cookieHeader;

    public static function setHeader($label, $value = '', $append = true)
    {
        $label = preg_replace('/[^a-zA-Z0-9-]/', '', $label);
        $label = ucwords($label, "-");
        $value = str_replace(["\r", "\n"], '', trim($value));

        $header = self::$header;
        if ($label === 'Content-Type' && isset($header['Content-Type'])) {
            $append = false;
        }
        if ($append && $value !== '') {
            $header[$label][] = $value;
        } elseif (!$append && $value === '') {
            unset($header[$label]);
        } elseif (!$append) {
            $header[$label] = [$value];
        }
        self::$header = $header;
    }

    public static function getHeader($label = null)
    {
        if (is_null($label)) {
            return self::$header;
        }
        return self::$header[$label] ?? '';
    }

    public static function setCache($header, $value = null)
    {
        if (is_null($value)) {
            self::$cacheHeader = $header;
        } else {
            self::$cacheHeader[$header] = (array)$value;
        }
    }

    public static function getCache($label = null)
    {
        if (is_null($label)) {
            return self::$cacheHeader;
        }
        return self::$cacheHeader[$label] ?? [];
    }

    public static function setCookie($header, $value = null)
    {
        if (is_null($value)) {
            self::$cookieHeader = $header;
        } else {
            self::$cookieHeader[$header] = (array)$value;
        }
    }

    public static function getCookie($label = null)
    {
        if (is_null($label)) {
            return self::$cookieHeader;
        }
        return self::$cookieHeader[$label] ?? [];
    }
}
