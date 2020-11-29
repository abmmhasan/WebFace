<?php


namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Base\BaseResponse;
use InvalidArgumentException;

final class Response extends BaseResponse
{
    public static function __callStatic(string $response_type = 'render', array $parameters = [])
    {
        self::$instance = self::$instance ?? new self('', 200, []);
        $instance = self::$instance;

        if ($response_type === 'instance') {
            return $instance;
        } elseif ($response_type === 'status') {
            $instance->setStatus(...$parameters);
        } elseif (!in_array($response_type, $instance->applicableFormat)) {
            return false;
        }
        $instance->setContent($parameters[0], $response_type);
        if (isset($parameters[1])) {
            $instance->setStatus($parameters[1]);
        }
        if (isset($parameters[2])) {
            $instance->setHeaderByGroup((array)$parameters[2]);
        }
        return self::$instance;
    }

    public function __call($response_type, $parameters)
    {
        (self::$instance)->$response_type(...$parameters);
        return self::$instance;
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
    public function setCache(array $options): object
    {
        // Check if keys are applicable
        if ($diff = array_diff(array_keys($options), array_keys($this->applicableCaches))) {
            throw new InvalidArgumentException(
                sprintf('Response does not support the following options: "%s".', implode('", "', $diff))
            );
        }

        // Cache Control Directives
        self::setControlCache($options);

        // Vary
        self::setVary($options);

        // ETag
        self::setETag($options);

        // Time Flags
        self::setTimeFlag($options);

        return self::$instance;
    }

    /**
     * Cookie Setter
     *
     * @param $name
     * @param $value
     * @param int $max_age
     * @param array $same_site
     * @return BaseResponse
     */
    public function setCookie($name, $value, int $max_age = 0, array $same_site = [])
    {
        if ($same_site && !in_array($same_site, ['Strict', 'Lax', 'None'])) {
            throw new InvalidArgumentException(
                "Invalid SameSite value! It could be any of " . implode(',', ['Strict', 'Lax', 'None'])
            );
        }
        $this->responseCookies[$name]['value'] = $value;
        if ($max_age) {
            $this->responseCookies[$name]['options']['maxage'] = $max_age;
        }
        if ($same_site) {
            $this->responseCookies[$name]['options']['samesite'] = $same_site;
        }
        return self::$instance;
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

    /**
     * Set charset for response
     *
     * If omitted it will check request charset
     * Default UTF-8
     *
     * @param string $charset
     * @return BaseResponse
     */
    public function setCharset(string $charset)
    {
        $this->charset = $charset;
        return self::$instance;
    }

    public function send()
    {
        self::helloWorld();
    }
}