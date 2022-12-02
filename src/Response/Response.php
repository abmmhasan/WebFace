<?php

namespace AbmmHasan\WebFace\Response;

use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Response\Asset\HTTPResource;
use AbmmHasan\WebFace\Response\Asset\Repository;
use Exception;
use InvalidArgumentException;

class Response
{
    private Repository $repository;

    use Single;

    /**
     * Set default response date
     *
     * @throws Exception
     */
    public function __construct()
    {
        self::$instance ??= $this;
        $this->repository = Repository::instance();
    }

    /**
     * Set Status
     *
     * @param int $code
     * @return Response
     */
    public function status(int $code): Response
    {
        $this->repository->setStatus($code);
        return self::$instance;
    }

    /**
     * Set content for success response
     *
     * @param mixed $data
     * @param string|null $message
     * @return Response
     */
    public function content(
        mixed  $data,
        string $message = null
    ): Response
    {
        $this->repository->setContent('success', $message, $data);
        return self::$instance;
    }

    /**
     * Set content for success response
     *
     * @param mixed $data
     * @param string|null $message
     * @return Response
     */
    public function success(
        mixed  $data,
        string $message = null
    ): Response
    {
        $this->repository->setContent('success', $message, $data);
        return self::$instance;
    }

    /**
     * Set content for failed response
     *
     * @param string|null $message
     * @param mixed|null $data
     * @return Response
     */
    public function fail(
        string $message = null,
        mixed  $data = null
    ): Response
    {
        $this->repository->setContent('fail', $message, $data);
        return self::$instance;
    }

    /**
     * Set content for error response
     *
     * @param string|null $message
     * @param mixed|null $data
     * @param string|null $errorCode
     * @return Response
     */
    public function error(
        string $message = null,
        mixed  $data = null,
        string $errorCode = null
    ): Response
    {
        $additional = [];
        if (!empty($errorCode)) {
            $additional['code'] = $errorCode;
        }
        $this->repository->setContent('error', $message, $data, $additional);
        return self::$instance;
    }

    /**
     * Set elements of content
     *
     * @param array|string $keys
     * @param mixed|null $value
     * @param string $parent
     * @return Response
     * @throws Exception
     */
    public function contentElement(array|string $keys, mixed $value = null, string $parent = 'data'): Response
    {
        $this->repository->setContentElement($keys, $value, $parent);
        return self::$instance;
    }

    /**
     * Set Header
     *
     * @param string $label
     * @param string|null $value
     * @param bool $append
     * @return Response
     */
    public function header(string $label, ?string $value = null, bool $append = true): Response
    {
        $this->repository->setHeader($label, $value, $append);
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
        $controlCache = $this->repository->getCache('Cache-Control');
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
        if (isset($controlCache['private'])) {
            unset($controlCache['public']);
        }
        $this->repository->setCache('Cache-Control', $controlCache);
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
        $vary = $this->repository->getCache('Vary');
        if ($diff = array_diff(
            $options,
            [
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
        $this->repository->setCache('Vary', array_merge($vary, $options));
        return self::$instance;
    }

    /**
     * Set ETag
     *
     * @param bool $isWeak
     * @param string $tag default:auto (will be calculated automatically if 'auto' is passed as value)
     * @return Response
     */
    public function eTag(bool $isWeak = true, string $tag = 'auto'): Response
    {
        $tag = trim($tag);
        if (!str_starts_with($tag, '"')) {
            $tag = $this->prepareStringQuote($tag);
        }
        $this->repository->setCache('ETag', ($isWeak ? "W/" : '') . $tag);
        return self::$instance;
    }

    /**
     * Set Last Modified
     *
     * @param string $time
     * @return Response
     * @throws Exception
     */
    public function lastModified(string $time): Response
    {
        $this->repository->setCache('Last-Modified', httpDate($time));
        return self::$instance;
    }

    /**
     * Set cookies
     *
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies
     *
     * @param array $asset [name_1 => value_1, name_2 => value_2,...]
     * @param array $options [applicable array keys: path, samesite, expires, domain,]
     * @return Response
     * @throws Exception
     */
    public function cookie(array $asset, array $options = []): Response
    {
        if (!empty($options['expires']) && !is_int($options['expires'])) {
            throw new Exception("Invalid Expire value!");
        }
        if (!empty($options['path']) && filter_var('https://www.example.com' . $options['path'], FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid Path!");
        }
        if (!empty($options['domain']) && filter_var($options['domain'], FILTER_VALIDATE_DOMAIN)) {
            throw new Exception("Invalid Domain!");
        }
        if (!empty($options['samesite'])) {
            if (!in_array($options['samesite'], ['Strict', 'Lax', 'None'])) {
                throw new Exception("Invalid SameSite value!");
            }
            if ($options['samesite'] === 'None' && URL::instance()->get('scheme') !== 'https') {
                throw new Exception("Cookie with 'SameSite=None' attribute, must use HTTPS protocol!");
            }
        }
        foreach ($asset as $name => $value) {
            $this->repository->setCookie($name, $value, $options);
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
        $this->repository->$charset = $charset;
        return self::$instance;
    }

    /**
     * Add Quote to a string
     *
     * @param string $string
     * @return string
     */
    public function prepareStringQuote(string $string): string
    {
        $string = preg_replace('/\\\\(.)|"/', '$1', trim($string));
        return '"' . addcslashes($string, '"\\"') . '"';
    }
}
