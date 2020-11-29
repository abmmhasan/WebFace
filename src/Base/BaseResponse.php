<?php


namespace AbmmHasan\WebFace\Base;


use AbmmHasan\WebFace\Utility\HTTPResource;
use ArrayObject;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Class BaseResponse
 * @package Inspect\Core\BaseRequest
 */
class BaseResponse extends BaseRequest
{
    /**
     * @var BaseResponse
     */
    protected static $instance;
    /**
     * @var array|mixed
     */
    private $code_phrase;
    /**
     * @var array|mixed
     */
    protected $applicableCaches = [];
    /**
     * @var mixed|string
     */
    private $protocolVersion;
    /**
     * @var string
     */
    protected $responseStatus;
    /**
     * @var int
     */
    protected $responseCode;
    /**
     * @var string
     */
    protected $charset;
    /**
     * @var array|string[]
     */
    protected $applicableFormat = [
        'render',
        'json',
        'xml',
        'csv'
    ];
    /**
     * @var
     */
    protected $responseContent;
    /**
     * @var array
     */
    protected $responseHeaders;
    /**
     * @var array
     */
    protected $responseCache;
    /**
     * @var array
     */
    protected $responseCookies = [];

    /**
     * BaseResponse constructor.
     *
     * @param $content
     * @param $status
     * @param $headers
     * @throws \Exception
     */
    public function __construct($content, $status, $headers)
    {
        parent::__construct();
        $this->code_phrase = HTTPResource::$statusList;
        $this->protocolVersion = HTTPResource::$responseVersion;
        $this->applicableCaches = HTTPResource::$cache;
        $this->responseCache['Date'] = httpDate();
        self::setStatus($status);
        self::setContent($content);
        self::setHeaderByGroup($headers);
    }

    /**
     * Set Status
     *
     * @param $code
     */
    protected function setStatus($code)
    {
        $code = (int)$code;
        if (!in_array($code, array_keys($this->code_phrase))) {
            throw new InvalidArgumentException("Invalid status code {$code}!");
        }
        $phrase = $this->code_phrase[$code] ?? null;
        $this->responseStatus = "HTTP/{$this->protocolVersion} {$code} {$phrase}";
        $this->responseCode = $code;
    }

    /**
     * Set Header
     *
     * @param $label
     * @param string $value
     * @param bool $append
     * @return BaseResponse
     */
    public function setHeader($label, $value = '', $append = true)
    {
        $label = preg_replace('/[^a-zA-Z0-9-]/', '', $label);
        $label = ucwords(strtolower($label), "-");
        $value = str_replace(["\r", "\n"], '', trim($value));

        if ($append && $value !== '') {
            $this->responseHeaders[$label][] = $value;
        } elseif (!$append && $value === '') {
            unset($this->responseHeaders[$label]);
        } elseif (!$append) {
            $this->responseHeaders[$label] = [$value];
        }
        return self::$instance;
    }

    /**
     * Set Content
     *
     * @param $content
     * @param $type
     * @return bool
     */
    protected function setContent($content, $type = 'html')
    {
        if ($type === 'json' || self::isJsonAble($content)) {
            self::setHeader('Content-Type', 'application/json', false);
            $this->responseContent = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return true;
        }
        $this->responseContent = $content;
        return true;
    }

    /**
     * Check if content is JSON convertible
     *
     * @param $content
     * @return bool
     */
    private function isJsonAble($content)
    {
        return $content instanceof ArrayObject ||
            $content instanceof JsonSerializable ||
            is_array($content);
    }

    /**
     * Set multiple headers at a time
     *
     * @param array $headers
     * @return BaseResponse
     */
    public function setHeaderByGroup(array $headers)
    {
        foreach ($headers as $name => $value) {
            self::setHeader($name, $value, false);
        }
        return self::$instance;
    }

    /**
     * Preparing standard response
     *
     * RFC 2616
     *
     * @return bool
     */
    protected function prepare()
    {
        $isEmpty = self::emptyResponse();
        $isUnmodified = self::notModified();
        if ($isEmpty || $isUnmodified) {
            return true;
        }

        // Content-type based on the Request
        if (!isset($this->responseHeaders['Content-Type'])) {
            $format = $this->headers['Content-Type'][0] ?? $this->contentHeader['type'] ?? '';
            if (self::getTypeHeader($format)) {
                self::setHeader('Content-Type', $format, false);
            }
        }
        // Fix Content-Type
        $responseCharset = $this->charset ?? $this->contentHeader['charset'] ?? 'UTF-8';
        if (!isset($this->responseHeaders['Content-Type'])) {
            self::setHeader('Content-Type', 'text/html; charset=' . $responseCharset, false);
        } elseif (0 === stripos($this->responseHeaders['Content-Type'][0], 'text/') && false === stripos(
                $this->responseHeaders['Content-Type'][0],
                'charset'
            )) {
            self::setHeader(
                'Content-Type',
                $this->responseHeaders['Content-Type'][0] . '; charset=' . $responseCharset,
                false
            );
        }

        // Fix Content-Length
        if (isset($this->responseHeaders['Transfer-Encoding'])) {
            unset($this->responseHeaders['Content-Length']);
        }

        if ($this->method === 'HEAD') {
            $length = $this->responseHeaders['Content-Length'][0];
            $this->responseContent = null;
            if ($length) {
                self::setHeader('Content-Length', $length, false);
            }
        }
        return true;
    }

    private function checkIfIntact()
    {
        $intact = false;
        if (in_array($this->method, ['GET', 'HEAD'])) {
            $lastModified = $this->responseHeaders['Last-Modified'] ?? null;
            $notModifiedSince = $this->dependencyHeader['if_unmodified_since'];
            if (!empty($this->dependencyHeader['if_match'])) {
                $intact = in_array(
                        $this->responseCache['ETag'] ?? '*',
                        $this->dependencyHeader['if_match']
                    ) || in_array('*', $this->dependencyHeader['if_match']);
            }
            if ($notModifiedSince && $lastModified && empty($this->dependencyHeader['if_match'])) {
                $intact = strtotime($notModifiedSince) >= strtotime($lastModified);
            }
            if ((!empty($this->dependencyHeader['if_match']) || $notModifiedSince) && !$intact) {
                self::setStatus(412);
                self::processResponse();
            }
        }
        return $intact;
    }

    /**
     * Check if response body should be empty or not, depending on Status code
     *
     * @return bool
     */
    private function emptyResponse()
    {
        if (($this->responseCode >= 100 && $this->responseCode < 200) || in_array($this->responseCode, [204, 304])) {
            $this->responseContent = null;
            unset($this->responseHeaders['Content-Type']);
            unset($this->responseHeaders['Content-Length']);
            ini_set('default_mimetype', '');
            return true;
        }
        return false;
    }

    /**
     * Check if the response is eligible and Should be set as not modified
     *
     * RFC 2616
     *
     * @return bool
     */
    private function notModified()
    {
        $notModified = false;
        if (in_array($this->method, ['GET', 'HEAD'])) {
            $lastModified = $this->responseHeaders['Last-Modified'] ?? null;
            $modifiedSince = $this->dependencyHeader['if_modified_since'];
            if (!empty($this->dependencyHeader['if_none_match'])) {
                $notModified = in_array(
                        $this->responseCache['ETag'] ?? '*',
                        $this->dependencyHeader['if_none_match']
                    ) || in_array('*', $this->dependencyHeader['if_none_match']);
            }
            if ($modifiedSince && $lastModified) {
                $notModified = strtotime($modifiedSince) >= strtotime($lastModified) &&
                    (empty($this->dependencyHeader['if_none_match']) || $notModified);
            }
            if ($notModified) {
                self::setStatus(304);
                self::processResponse();
            }
        }
        return $notModified;
    }

    private function processResponse()
    {
        $this->responseContent = null;
        foreach (
            [
                'Allow',
                'Content-Encoding',
                'Content-Language',
                'Content-Length',
                'Content-MD5',
                'Content-Type',
                'Last-Modified'
            ] as $header
        ) {
            unset($this->responseHeaders[$header]);
        }
    }

    /**
     * Control Directives
     *
     * Easy Understanding: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     * Stale Caching: https://www.keycdn.com/blog/keycdn-supports-stale-while-revalidate
     *
     * @param $options
     */
    protected function setControlCache($options)
    {
        foreach ($this->applicableCaches as $directive => $hasValue) {
            if ($hasValue && isset($options[$directive])) {
                if ($options[$directive]) {
                    $this->responseCache['control'][$directive] = str_replace('_', '-', $directive);
                    continue;
                }
                unset($this->responseCache['control'][$directive]);
            }
        }

        // Setting up cache type: public(if need intermediate caching) or private
        if (isset($options['private']) && $options['private']) {
            $this->responseCache['control']['visibility'] = 'private';
        } elseif (isset($options['public']) && $options['public']) {
            // Required for CDN
            $this->responseCache['control']['visibility'] = 'public';
        }

        if (isset($options['max_age'])) {
            $this->responseCache['control']['max_age'] = 'max-age=' . $options['max_age'];
        }

        if (isset($options['s_maxage'])) {
            $this->responseCache['control']['s_maxage'] = 's-maxage=' . $options['s_maxage'];
        }

        if (isset($options['stale_while_revalidate'])) {
            $this->responseCache['control']['stale_while_revalidate'] = 'stale-while-revalidate=' . $options['stale_while_revalidate'];
        }

        if (isset($options['stale_while_revalidate'])) {
            $this->responseCache['control']['stale_while_revalidate'] = 'stale-while-revalidate=' . $options['stale_while_revalidate'];
        }
    }

    /**
     * Vary Header
     *
     * Easy Understanding: https://www.keycdn.com/support/vary-header
     * What & How: https://www.smashingmagazine.com/2017/11/understanding-vary-header/
     * Accepted Client Hints: https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/client-hints
     * Why validation: https://www.fastly.com/blog/best-practices-using-vary-header
     *
     * @param $options
     */
    protected function setVary($options)
    {
        if (isset($options['vary'])) {
            if (null === $options['vary']) {
                unset($this->responseCache['Vary']);
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
                $this->responseCache['special']['Vary'] = (array)$options['vary'];
            }
        }
    }

    /**
     * Set ETag
     *
     * @param $options
     */
    protected function setETag($options)
    {
        if (isset($options['etag'])) {
            if (null === $options['etag']) {
                unset($this->responseCache['ETag']);
            } else {
                if (0 !== strpos($options['etag'], '"')) {
                    $options['etag'] = $this->prepareStringQuote($options['etag']);
                }
                $this->responseCache['ETag'] = trim($options['etag']);
            }
        }
    }

    /**
     * Add Quote to a string
     *
     * @param string $string
     * @return string|string[]|null
     */
    protected function prepareStringQuote(string $string)
    {
        $string = preg_replace('/\\\\(.)|"/', '$1', trim($string));
        if (preg_match('/^[a-z0-9!#$%&\'*.^_`|~-]+$/i', $string)) {
            return $string;
        }
        return '"' . addcslashes($string, '"\\"') . '"';
    }

    /**
     * Set Last Modified, Date, Age
     *
     * @param $options
     * @throws \Exception
     */
    protected function setTimeFlag($options)
    {
        if (isset($options['last_modified'])) {
            if (null === $options['last_modified']) {
                unset($this->responseCache['Last-Modified']);
            } else {
                $this->responseCache['Last-Modified'] = httpDate($options['last_modified']);
            }
        }

        if (isset($options['date'])) {
            if (null === $options['date']) {
                unset($this->responseCache['Date']);
            } else {
                $this->responseCache['Date'] = httpDate($options['date']);
            }
        }

        $this->responseCache['Age'] = max(time() - strtotime($this->responseCache['Date'] ?? httpDate()), 0);
    }

    /**
     * Cache Control Calculator
     *
     * This will set the Control directives to desired standard
     *
     * @return mixed|string[]
     */
    private function computeCacheControl()
    {
        if (empty($this->responseCache['control'])) {
            if (isset($this->responseCache['Last-Modified'])) {
                return ['private', 'must-revalidate'];
            }
            return ['no-cache', 'private'];
        }

        if (!$this->isSharedCacheable()) {
            unset($this->responseCache['control']['s-maxage']);
        }

        if (isset($this->responseCache['control']['s-maxage'])) {
            $this->responseCache['control']['visibility'] = 'public';
        }

        if (isset($this->responseCache['control']['visibility'])) {
            return $this->responseCache['control'];
        }

        if (!isset($this->responseCache['control']['s-maxage'])) {
            $this->responseCache['control']['visibility'] = 'private';
            return $this->responseCache['control'];
        }
    }

    /**
     * Preparing cache headers
     *
     * RFC 7231, RFC 7234, RFC 8674
     *
     * @return bool
     */
    protected function prepareCacheHeader()
    {
        if (in_array($this->method, ['GET', 'HEAD', 'POST']) &&
            in_array($this->responseCode, [200, 203, 204, 206, 300, 404, 405, 410, 414, 501])) {
            $control = array_values(self::computeCacheControl());
            if (!empty($control)) {
                self::setHeader('Cache-Control', implode(',', $control));
            }
        }
        unset($this->responseCache['control']);
        if ($this->dependencyHeader['prefer_safe']) {
            $this->responseCache['special']['Vary'][] = 'Prefer';
            self::setHeader('Preference-Applied', 'safe');
        }
        if (isset($this->responseCache['special'])) {
            foreach ($this->responseCache['special'] as $label => $value) {
                self::setHeader($label, implode(',', $value));
            }
            unset($this->responseCache['special']);
        }
        foreach ($this->responseCache as $label => $value) {
            self::setHeader($label, $value);
        }
        return true;
    }

    /**
     * Check if the response is intermediary cacheable
     *
     * RFC 7231
     *
     * @return bool
     */
    public function isSharedCacheable(): bool
    {
        if (!in_array($this->responseCode, [200, 203, 300, 301, 302, 404, 410])) {
            return false;
        }

        if (isset($this->responseCache['control']['no_store']) ||
            $this->responseCache['control']['visibility'] == 'private') {
            return false;
        }

        $maxAge = $this->responseCache['control']['s-maxage'] ?? $this->responseCache['control']['max-age'] ?? null;

        $fresh = (null !== $maxAge ? $maxAge - $this->responseCache['Age'] : null) > 0;

        return isset($this->responseCache['Last-Modified']) || $fresh;
    }

    /**
     * Checks if eligible header found
     *
     * This is still experimental
     *
     * @param $type
     * @return bool
     */
    private function getTypeHeader($type)
    {
        $heads = [
            'text/html',
            'application/xhtml+xml',
            'text/plain',
            'application/javascript',
            'application/x-javascript',
            'text/javascript',
            'text/css',
            'application/json',
            'application/x-json',
            'application/ld+json',
            'text/xml',
            'application/xml',
            'application/x-xml',
            'application/rdf+xml',
            'application/atom+xml',
            'application/rss+xml',
            'application/x-www-form-urlencoded'
        ];
        return in_array($type, $heads);
//        $heads = [
//            'html' => ['text/html', 'application/xhtml+xml'],
//            'txt' => ['text/plain'],
//            'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'],
//            'css' => ['text/css'],
//            'json' => ['application/json', 'application/x-json'],
//            'jsonld' => ['application/ld+json'],
//            'xml' => ['text/xml', 'application/xml', 'application/x-xml'],
//            'rdf' => ['application/rdf+xml'],
//            'atom' => ['application/atom+xml'],
//            'rss' => ['application/rss+xml'],
//            'form' => ['application/x-www-form-urlencoded'],
//        ];
//        return $all ? ($heads[$type] ?? []) : ($heads[$type][0] ?? null);
    }

    /**
     * Prepare & Send all the headers & Cookies
     *
     * @throws \Exception
     */
    protected function sendHeaders()
    {
        // headers have already been sent
        if (headers_sent()) {
            return;
        }

        // Set Status Header
        header($this->responseStatus, true, $this->responseCode);

        // headers
        foreach ($this->responseHeaders as $name => $values) {
            $replace = 0 === strcasecmp($name, 'Content-Type');
            header($name . ': ' . implode(',', $values), $replace, $this->responseCode);
        }

        header('X-Powered-By: WebFace', true, $this->responseCode);

        // Set Cookies
        if (!empty($this->responseCookies)) {
            // ToDo: Move Config & SERVER_TIME
            $cookie_config = Config::get("session");
            $expire = httpDate(date(DATE_ATOM, $_SERVER['REQUEST_TIME'] + ($cookie_config["lifetime"] * 60)));
            $isSecure = (bool)$cookie_config["secure"] && $this->url->scheme === 'https';
            foreach ($this->responseCookies as $name => $cookie) {
                setcookie(
                    $name,
                    $cookie['value'],
                    [
                        'expires' => $expire,
                        'path' => $cookie_config["path"],
                        'domain' => $cookie_config["domain"],
                        'secure' => $isSecure,
                        'httponly' => (bool)$cookie_config["http_only"]
                    ] + ($this->responseCookies[$name]['options'] ?? [])
                );
            }
        }
    }

    /**
     * Sends output
     *
     * @throws \Exception
     */
    protected function helloWorld()
    {
        if (!self::checkIfIntact()) {
            self::prepareCacheHeader();
            self::prepare();
        }
        self::sendHeaders();
        echo $this->responseContent;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $targetLevel = 0;
            $flush = true;
            $status = ob_get_status(true);
            $level = count($status);
            $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);

            while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
                if ($flush) {
                    ob_end_flush();
                } else {
                    ob_end_clean();
                }
            }
        }
    }
}