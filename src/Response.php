<?php

namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Support\HTTPResource;
use AbmmHasan\WebFace\Support\ResponseDepot;
use Exception;
use InvalidArgumentException;

final class Response
{
    private static Response $instance;

    protected array $applicableFormat = [
        'render',
        'json',
        'csv'
    ];

    /**
     * Set default response date
     *
     * @param string|array|null $content
     * @param int $status
     * @param array $headers
     * @throws Exception
     */
    public function __construct(string|array $content = null, int $status = 200, array $headers = [])
    {
        self::$instance ??= $this;
        $this->status($status);
        $this->withHeaders($headers);
        ResponseDepot::setContent($content);
    }

    /**
     * Get Class instance
     *
     * @param string|array|null $content
     * @param int $status
     * @param array $headers
     * @return Response
     * @throws Exception
     */
    public static function instance(string|array $content = null, int $status = 200, array $headers = []): Response
    {
        return self::$instance ??= new self($content, $status, $headers);
    }

    /**
     * Set Status
     *
     * @param int $code
     * @return Response
     */
    public function status(int $code): Response
    {
        ResponseDepot::setStatus($code);
        return self::$instance;
    }

    /**
     * Set Header
     *
     * @param string $label
     * @param string $value
     * @param bool $append
     * @return Response
     */
    public function header(string $label, string $value = '', bool $append = true): Response
    {
        ResponseDepot::setHeader($label, $value, $append);
        return self::$instance;
    }

    /**
     * Set multiple headers at a time
     *
     * Name => Value
     *
     * @param array $headers
     * @return Response
     */
    public function withHeaders(array $headers): Response
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value, false);
        }
        return self::$instance;
    }

    /**
     * Control Directives
     *
     * Easy Understanding: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * Stale Caching: https://www.keycdn.com/blog/keycdn-supports-stale-while-revalidate
     *
     * @param array $options
     * @return Response
     */
    public function cacheControl(array $options): Response
    {
        $controlCache = ResponseDepot::getCache('Cache-Control');
        foreach ($options as $item => $value) {
            if (isset(HTTPResource::$cacheControl[$value])) {
                if (HTTPResource::$cacheControl[$value]) {
                    $controlCache[$value] = $value;
                    continue;
                }
                unset($controlCache[$value]);
                continue;
            }
            if (isset(HTTPResource::$cacheControl[$item])) {
                if (!HTTPResource::$cacheControl[$item]) {
                    $controlCache[$item] = "$item=" . intval($value);
                    continue;
                }
                unset($controlCache[$item]);
            }
        }
        ResponseDepot::setCache('Cache-Control', $controlCache);
        return self::$instance;
    }

    /**
     * Vary Header
     *
     * Easy Understanding: https://www.keycdn.com/support/vary-header
     * What & How: https://www.smashingmagazine.com/2017/11/understanding-vary-header/
     * Accepted Client Hints: https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/client-hints
     * Why validation: https://www.fastly.com/blog/best-practices-using-vary-header
     *
     * @param array $options
     * @return Response
     */
    public function vary(array $options): Response
    {
        $vary = ResponseDepot::getCache('Vary');
        if ($diff = array_diff(
            $options,
            [
                'Accept-Encoding',
                'Accept-Language',
                'DPR',
                'Content-DPR',
                'Width',
                'Viewport-Width',
                'Device-Memory',
                'RTT',
                'Downlink',
                'ECT',
                'Save-Data'
            ]
        )) {
            throw new InvalidArgumentException(
                sprintf("Vary header can't be used with the following options: '%s'.", implode('", "', $diff))
            );
        }
        ResponseDepot::setCache('Vary', array_merge($vary, $options));
        return self::$instance;
    }

    /**
     * Set Modifiers (Last Modified, ETag)
     *
     * @param array $options
     * @param bool $weakETag
     * @return Response
     * @throws Exception
     */
    public function modifier(array $options, bool $weakETag = false): Response
    {
        if (isset($options['Last-Modified'])) {
            ResponseDepot::setCache('Last-Modified', httpDate($options['Last-Modified']));
        }
        if (!empty($options['ETag'])) {
            $options['ETag'] = trim($options['ETag']);
            if (!str_starts_with($options['ETag'], '"')) {
                $options['ETag'] = $this->prepareStringQuote($options['ETag']);
            }
            ResponseDepot::setCache(
                'ETag',
                ($weakETag ? "W/" : '') . $options['ETag']
            );
        }
        return self::$instance;
    }

    /**
     * Set cookies
     *
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies
     *
     * @param array $asset [name => value]
     * @param int|null $max_age
     * @param string $same_site
     * @return Response
     */
    public function cookie(array $asset, int $max_age = null, string $same_site = 'Lax'): Response
    {
        if (!empty($same_site) && !in_array($same_site, ['Strict', 'Lax', 'None'])) {
            throw new InvalidArgumentException(
                "Invalid SameSite value! It could be any of " . implode(',', ['Strict', 'Lax', 'None'])
            );
        }
        foreach ($asset as $name => $value) {
            $cookies = [
                'value' => $value,
                'samesite' => $same_site
            ];
            if ($max_age !== null) {
                $cookies['maxage'] = $max_age;
            }
            ResponseDepot::setCookie($name, $cookies);
        }
        return self::$instance;
    }

    /**
     * Set charset for response
     *
     * If omitted it will check request charset
     * Default UTF-8
     *
     * @param string $charset
     * @return Response
     */
    public function charset(string $charset): Response
    {
        ResponseDepot::$charset = $charset;
        return self::$instance;
    }

    /**
     * Add Quote to a string
     *
     * @param string $string
     * @return string
     */
    private function prepareStringQuote(string $string): string
    {
        $string = preg_replace('/\\\\(.)|"/', '$1', trim($string));
        return '"' . addcslashes($string, '"\\"') . '"';
    }
}