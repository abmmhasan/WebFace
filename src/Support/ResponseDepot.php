<?php


namespace AbmmHasan\WebFace\Support;


final class ResponseDepot
{
    public static int $code = 200;
    public static string $contentType = 'json';
    public static string|array $content = '';
    public static string $charset = 'UTF-8';

    private static array $header = [];
    private static array $cacheHeader = [];
    private static array $cookieHeader = [];

    /**
     * Set header
     *
     * @param $label
     * @param string $value
     * @param bool $append
     * @return void
     */
    public static function setHeader($label, string $value = '', bool $append = true)
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
     * @param string $type
     * @return void
     */
    public static function setContent(string|array $content, string $type = 'json')
    {
        self::$contentType = $type;
        self::$content = $content;
    }

    /**
     * Get content/body & type
     *
     * @return array
     */
    public static function getContent(): array
    {
        return [
            'type' => self::$contentType,
            'content' => self::$content,
        ];
    }

    /**
     * Set cache header
     *
     * @param array|string $header
     * @param string|null $value
     * @return void
     */
    public static function setCache(array|string $header, ?string $value = null)
    {
        if (is_null($value)) {
            self::$cacheHeader = $header;
        } else {
            self::$cacheHeader[$header] = (array)$value;
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
     * @param string|null $value
     * @return void
     */
    public static function setCookie(array|string $header, ?string $value = null)
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
