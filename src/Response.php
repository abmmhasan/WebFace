<?php


namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Base\BaseResponse;
use AbmmHasan\WebFace\Support\HTTPResource;
use AbmmHasan\WebFace\Support\ResponseDepot;
use InvalidArgumentException;

final class Response
{
    private static $instance;
    /**
     * @var array|string[]
     */
    protected $applicableFormat = [
        'render',
        'json',
        'xml',
        'csv'
    ];

    public function __construct()
    {
        $modCache = ResponseDepot::getCache();
        $modCache['Date'] = httpDate();
        ResponseDepot::setCache($modCache);
    }

    public static function __callStatic(string $response_type, array $parameters = [])
    {

        self::$instance = self::$instance ?? new self();

        if ($response_type === 'instance') {
            return self::$instance;
        } elseif ($response_type === 'status') {
            (self::$instance)->setStatus(...$parameters);
        } elseif (!in_array($response_type, (self::$instance)->applicableFormat)) {
            throw new \Exception("Unknown reponse type '$response_type' detected!");
        }
        (self::$instance)->setContent($parameters[0], $response_type);
        if (isset($parameters[1])) {
            (self::$instance)->setStatus($parameters[1]);
        }
        if (isset($parameters[2])) {
            (self::$instance)->setHeaderByGroup((array)$parameters[2]);
        }
        return self::$instance;
    }

    public function __call($response_type, $parameters)
    {
        (self::$instance)->$response_type(...$parameters);
        return self::$instance;
    }

    /**
     * Set Header
     *
     * @param $label
     * @param string $value
     * @param bool $append
     */
    private function setHeader($label, $value = '', $append = true)
    {
        ResponseDepot::setHeader($label, $value, $append);
    }

    /**
     * Set Status
     *
     * @param $code
     */
    private function setStatus($code)
    {
        $code = (int)$code;
        if (!in_array($code, array_keys(HTTPResource::$statusList))) {
            throw new InvalidArgumentException("Invalid status code {$code}!");
        }
        ResponseDepot::$code = $code;
    }

    /**
     * Set multiple headers at a time
     *
     * @param array $headers
     */
    private function setHeaderByGroup(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value, false);
        }
    }

    /**
     * Set Content
     *
     * @param $content
     * @param $type
     */
    private function setContent($content, $type)
    {
        ResponseDepot::$contentType = $type;
        ResponseDepot::$content = $content;
    }

    /**
     * Set Cache Headers
     *
     * Easy Understanding: https://www.keycdn.com/blog/http-cache-headers
     *
     * @param array $options
     * @return object
     * @throws \Exception
     */
    private function setCache(array $options): object
    {
        // Check if keys are applicable
        if ($diff = array_diff(array_keys($options), array_keys(HTTPResource::$cache))) {
            throw new InvalidArgumentException(
                sprintf('Response does not support the following options: "%s".', implode('", "', $diff))
            );
        }

        // Cache Control Directives
        self::setControlCache($options);

        // Special Headers
        self::setSpecial($options);

        // Modifiers
        self::setModifier($options);
    }

    /**
     * Control Directives
     *
     * Easy Understanding: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * Stale Caching: https://www.keycdn.com/blog/keycdn-supports-stale-while-revalidate
     *
     * @param $options
     */
    private function setControlCache($options)
    {
        $controlCache = ResponseDepot::getCache('control');
        foreach (HTTPResource::$cache as $directive => $hasValue) {
            if ($hasValue && isset($options[$directive])) {
                if ($options[$directive]) {
                    $controlCache[$directive] = str_replace('_', '-', $directive);
                    continue;
                }
                unset($controlCache[$directive]);
            }
        }

        // Setting up cache type: public(if need intermediate caching) or private
        if (isset($options['private']) && $options['private']) {
            $controlCache['visibility'] = 'private';
        } elseif (isset($options['public']) && $options['public']) {
            // Required for CDN
            $controlCache['visibility'] = 'public';
        }

        if (isset($options['max_age'])) {
            $controlCache['max_age'] = 'max-age=' . $options['max_age'];
        }

        if (isset($options['s_maxage'])) {
            $controlCache['s_maxage'] = 's-maxage=' . $options['s_maxage'];
        }

        if (isset($options['stale_while_revalidate'])) {
            $controlCache['stale_while_revalidate'] = 'stale-while-revalidate=' . $options['stale_while_revalidate'];
        }

        if (isset($options['stale_while_revalidate'])) {
            $controlCache['stale_while_revalidate'] = 'stale-while-revalidate=' . $options['stale_while_revalidate'];
        }
        ResponseDepot::setCache('control', $controlCache);
    }

    /**
     * Special Headers
     * ***************
     * Vary Header
     *
     * Easy Understanding: https://www.keycdn.com/support/vary-header
     * What & How: https://www.smashingmagazine.com/2017/11/understanding-vary-header/
     * Accepted Client Hints: https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/client-hints
     * Why validation: https://www.fastly.com/blog/best-practices-using-vary-header
     *
     * @param $options
     */
    private function setSpecial($option)
    {
        $specialCache = ResponseDepot::getCache('special');
        if (isset($options['vary'])) {
            if (null === $options['vary']) {
                unset($specialCache['Vary']);
            } else {
                if ($diff = array_diff(
                    (array)$options['vary'],
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
                        sprintf("Vary header can't be used the following options: '%s'.", implode('", "', $diff))
                    );
                }
                $specialCache['Vary'] = (array)$options['vary'];
            }
        }
        ResponseDepot::setCache('special', $specialCache);
    }

    /**
     * Set Modifiers
     *
     * Last Modified, Date, ETag
     *
     * @param $options
     * @throws \Exception
     */
    private function setModifier($options)
    {
        $modCache = ResponseDepot::getCache();
        if (isset($options['last_modified'])) {
            if (null === $options['last_modified']) {
                unset($modCache['Last-Modified']);
            } else {
                $modCache['Last-Modified'] = httpDate($options['last_modified']);
            }
        }

        if (isset($options['etag'])) {
            if (null === $options['etag']) {
                unset($modCache['ETag']);
            } else {
                if (0 !== strpos($options['etag'], '"')) {
                    $options['etag'] = $this->prepareStringQuote($options['etag']);
                }
                $modCache['ETag'] = trim($options['etag']);
            }
        }

        if (isset($options['date']) && !is_null($options['date'])) {
            $modCache['Date'] = httpDate($options['date']);
        }
        ResponseDepot::setCache($modCache);
    }

    /**
     * Add Quote to a string
     *
     * @param string $string
     * @return string|string[]|null
     */
    private function prepareStringQuote(string $string)
    {
        $string = preg_replace('/\\\\(.)|"/', '$1', trim($string));
        return '"' . addcslashes($string, '"\\"') . '"';
    }


    /**
     * Cookie Setter
     *
     * @param $name
     * @param $value
     * @param int|null $max_age
     * @param array $same_site
     */
    private function setCookie($name, $value, int $max_age = null, array $same_site = [])
    {
        if (!empty($same_site) && !in_array($same_site, ['Strict', 'Lax', 'None'])) {
            throw new InvalidArgumentException(
                "Invalid SameSite value! It could be any of " . implode(',', ['Strict', 'Lax', 'None'])
            );
        }
        $cookies = ResponseDepot::getCookie($name);
        $cookies['value'] = $value;
        if (!empty($max_age)) {
            $cookies['maxage'] = $max_age;
        }
        if (!empty($same_site)) {
            $cookies['samesite'] = $same_site;
        } else {
            $cookies['samesite'] = 'Lax';
        }
        ResponseDepot::setCookie($name, $cookies);
    }

    /**
     * Set charset for response
     *
     * If omitted it will check request charset
     * Default UTF-8
     *
     * @param string $charset
     */
    private function setCharset(string $charset)
    {
        ResponseDepot::$charset = $charset;
    }

    /**
     * Set disposition content
     *
     * RFC 6266
     *
     * @param string $disposition
     * @param string $filename
     * @param string $filenameFallback
     * @return BaseResponse
     */
    public function setDisposition(string $disposition, string $filename = '', string $filenameFallback = '')
    {
        if (!in_array($disposition, ['inline', 'attachment'])) {
            throw new \InvalidArgumentException('The disposition must be either "inline" or "attachment".');
        }

        if ('' === $filenameFallback) {
            $filenameFallback = $filename;
        }

        if (!preg_match('/^[\x20-\x7e]*$/', $filenameFallback) || false !== strpos($filenameFallback, '%')) {
            throw new \InvalidArgumentException('The filename fallback must only contain ASCII (except %) characters.');
        }

        if (false !== strpos($filename, '/') || false !== strpos($filename, '\\') ||
            false !== strpos($filenameFallback, '/') || false !== strpos($filenameFallback, '\\')) {
            throw new \InvalidArgumentException(
                'The filename and the fallback cannot contain the "/" and "\\" characters.'
            );
        }

        $params[] = "filename=" . self::prepareStringQuote($filenameFallback);
        if ($filename !== $filenameFallback) {
            $params[] = "filename*=" . self::prepareStringQuote("utf-8''" . rawurlencode($filename));
        }
        self::setHeader('Content-Disposition', "{$disposition}; " . implode('; ', $params), false);
        return self::$instance;
    }
}
