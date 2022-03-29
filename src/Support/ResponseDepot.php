<?php


namespace AbmmHasan\WebFace\Support;


final class ResponseDepot
{
    public static int $code = 200;
    public static string $contentType = 'json';
    public static string $charset = 'UTF-8';

    private static array $header = [];
    private static array $cacheHeader = [];
    private static array $cookieHeader = [];
    private static string|array $content = '';

    /**
     * Set header
     *
     * @param $label
     * @param string|null $value
     * @param bool $append
     * @return void
     */
    public static function setHeader($label, ?string $value = null, bool $append = true)
    {
        $label = preg_replace('/[^a-zA-Z0-9-]/', '', $label);
        $label = ucwords($label, "-");
        $value = str_replace(["\r", "\n"], '', trim($value));

        $header = self::$header;
        if ($label === 'Content-Type' && isset($header['Content-Type'])) {
            $append = false;
        }
        if ($append && !empty($value)) {
            $header[$label][] = $value;
        } elseif (!$append && empty($value)) {
            unset($header[$label]);
        } elseif (!$append) {
            $header[$label] = [$value];
        }
        self::$header = $header;
    }

    /**
     * Get header
     *
     * @param $label
     * @return array|mixed|string
     */
    public static function getHeader($label = null): mixed
    {
        if (is_null($label)) {
            return self::$header;
        }
        return self::$header[$label] ?? '';
    }

    /**
     * Set content/body & type
     *
     * @param string|array $content
     * @return void
     */
    public static function setContent(string|array $content)
    {
        self::$content = $content;
    }

    /**
     * Get content/body & type
     *
     * @return string|array
     */
    public static function getContent(): string|array
    {
        return self::$content;
    }

    /**
     * Set cache header
     *
     * @param array|string $header
     * @param string|array|null $value
     * @return void
     */
    public static function setCache(array|string $header, string|array|null $value = null)
    {
        if (is_array($header) && empty($value)) {
            self::$cacheHeader = $header;
        } elseif(is_string($header)) {
            self::$cacheHeader[$header] = $value;
        }
    }

    /**
     * Get cache header
     *
     * @param string|null $label
     * @return array|mixed
     */
    public static function getCache(?string $label = null): mixed
    {
        if (is_null($label)) {
            return self::$cacheHeader;
        }
        return self::$cacheHeader[$label] ?? [];
    }

    /**
     * Set cookie
     *
     * @param array|string $header
     * @param string|array|null $value
     * @return void
     */
    public static function setCookie(array|string $header, string|array|null $value = null)
    {
        if (is_null($value)) {
            self::$cookieHeader = $header;
        } else {
            self::$cookieHeader[$header] = (array)$value;
        }
    }

    /**
     * Get cookie
     *
     * @param string|null $label
     * @return mixed
     */
    public static function getCookie(?string $label = null): mixed
    {
        if (is_null($label)) {
            return self::$cookieHeader;
        }
        return self::$cookieHeader[$label] ?? [];
    }
}
