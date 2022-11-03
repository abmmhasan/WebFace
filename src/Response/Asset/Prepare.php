<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\WebFace\Request\Asset\Headers;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\Settings;
use ArrayObject;
use Exception;
use JsonSerializable;

final class Prepare
{
    private array $applicableStatus = [
        200 => 200,
        203 => 203,
        204 => 204,
        206 => 206,
        300 => 300,
        301 => 301,
        302 => 302,
        304 => 304,
        404 => 404,
        405 => 405,
        410 => 410,
        414 => 414,
        501 => 501
    ];

    private array $noContentEligible = [
        100 => 100,
        101 => 101,
        102 => 102,
        103 => 103,
        204 => 204,
        205 => 205,
        304 => 304,
    ];

    private array $applicableSharedStatus = [
        200 => 200,
        203 => 203,
        300 => 300,
        301 => 301,
        302 => 302,
        404 => 404,
        410 => 410
    ];

    use Single;

    /**
     * Preparing cache headers
     *
     * RFC 7231, RFC 7234, RFC 8674
     * https://learning.mlytics.com/the-internet/http-request-methods/
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching
     *
     * @return void
     * @throws Exception
     */
    public function cacheHeader(): void
    {
        $cache = ResponseDepot::getCache();
        if (isset($this->applicableStatus[ResponseDepot::getStatus()]) &&
            (URL::instance()->getMethod('converted') === 'GET' ||
                (URL::instance()->getMethod('converted') === 'POST' && ResponseDepot::getHeader('Content-Location'))
            )
        ) {
            $control = array_values($this->computeCacheControl($cache));
            if (!empty($control)) {
                ResponseDepot::setHeader('Cache-Control', implode(',', $control), false);
            }
        }
        unset($cache['Cache-Control']);
        if (!empty($cache)) {
            foreach ($cache as $label => $value) {
                ResponseDepot::setHeader($label, implode(',', (array)$value), false);
            }
        }
    }

    /**
     * Preparing standard response
     *
     * RFC 2616
     *
     * @return void
     * @throws Exception
     */
    public function contentAndCache(): void
    {
        ResponseDepot::setContent($this->contentParser(ResponseDepot::getContent()));
        $contentType = ResponseDepot::getHeader('Content-Type');
        $this->calculateEtag();

        $isUnmodified = $this->notModified();
        $isEmpty = $this->empty();
        if ($isEmpty || $isUnmodified) {
            return;
        }

        // Content-type based on the Request
        if ($contentType === null) {
            $applicable = array_intersect(ResponseDepot::$applicableFormat, (array)Headers::instance()->content('type'));
            if (!empty($applicable)) {
                ResponseDepot::setHeader('Content-Type', current($applicable), false);
            } else {
                $charset = ResponseDepot::$charset ?? 'UTF-8';
                ResponseDepot::setHeader('Content-Type', 'text/html; charset=' . $charset, false);
            }
        }

        if (ResponseDepot::getStatus() < 400 &&
            (empty($contentType) || !$applicableViaAccept = array_intersect(
                    array_merge($contentType, ['*/*']),
                    Headers::instance()->accept('Accept')
                ))
        ) {
            ResponseDepot::setStatus(406);
        }

        // Fix Content-Length; ToDo: WIP
        if (isset($setHeaders['Transfer-Encoding'])) {
            ResponseDepot::setHeader('Content-Length', '', false);
        }
    }

    /**
     * Calculate Etag
     *
     * @return void
     */
    private function calculateEtag(): void
    {
        match (ResponseDepot::getCache('ETag')) {
            'W/"auto"' => ResponseDepot::setCache('ETag',
                'W/"' . hash(
                    Settings::$weakEtagMethod,
                    ResponseDepot::getContent()
                ) . '"'
            ),
            '"auto"' => ResponseDepot::setCache('ETag',
                '"' . hash(
                    Settings::$etagMethod,
                    json_encode(ResponseDepot::getHeader()) . ';' . ResponseDepot::getContent()
                ) . '"'
            ),
            default => null
        };
    }

    /**
     * Get response
     *
     * @param $content
     * @return string|bool
     * @throws Exception
     */
    public function contentParser($content): string|bool
    {
        if (
            is_array($content) ||
            $content instanceof JsonSerializable ||
            $content instanceof ArrayObject
        ) {
            ResponseDepot::setHeader('Content-Type', 'application/json', false);
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (!is_string($content)) {
            throw new Exception('Unable to parse content');
        }
        return $content;
    }

    /**
     * Cache Control Calculator
     *
     * This will set the Control directives to desired standard
     *
     * @return mixed|string[]
     */
    private function computeCacheControl($cache): mixed
    {
        if (!isset($cache['Cache-Control'])) {
            if (isset($cache['Last-Modified'])) {
                return ['private', 'must-revalidate'];
            }
            return ['private', 'no-cache'];
        }

        if (!$this->isSharedCacheable($cache)) {
            unset($cache['Cache-Control']['s-maxage']);
        }

        if (isset($cache['Cache-Control']['s-maxage'])) {
            $cache['Cache-Control']['public'] = 'public';
        }

        if (isset($cache['Cache-Control']['public']) || isset($cache['Cache-Control']['private'])) {
            return $cache['Cache-Control'];
        }
        $cache['Cache-Control']['private'] = 'private';
        return $cache['Cache-Control'];
    }

    /**
     * Check if the response is intermediary cacheable
     *
     * RFC 7231
     *
     * @param $cache
     * @return bool
     */
    private function isSharedCacheable($cache): bool
    {
        if (
            !isset($this->applicableSharedStatus[ResponseDepot::getStatus()]) ||
            isset($cache['Cache-Control']['no-store']) ||
            isset($cache['Cache-Control']['private'])
        ) {
            return false;
        }

        if (isset($cache['Last-Modified'])) {
            return true;
        }

        return ($cache['Cache-Control']['s-maxage'] ?? $cache['Cache-Control']['max-age'] ?? null) > 0;
    }

    /**
     * Check if the response is eligible and Should be set as not modified
     *
     * RFC 2616
     *
     * @return bool
     * @throws Exception
     */
    private function notModified(): bool
    {
        $notModified = false;
        if (ResponseDepot::getStatus() !== 304 && URL::instance()->getMethod('converted') === 'GET') {
            $cacheHeaders = ResponseDepot::getCache();
            $lastModified = $cacheHeaders['Last-Modified'] ?? null;
            $headers = Headers::instance();
            $modifiedSince = $headers->responseDependency('if_modified_since');
            if ($modifiedSince && $lastModified) {
                $notModified = strtotime($modifiedSince) >= strtotime($lastModified);
            }
            if (!$notModified && !empty($noneMatch = $headers->responseDependency('if_none_match'))) {
                $notModified = !!array_intersect($noneMatch, [$cacheHeaders['ETag'] ?? '*', '*']);
            }
            if ($notModified) {
                ResponseDepot::setStatus(304);
            }
        }
        return $notModified;
    }

    /**
     * Check if response body should be empty or not, depending on Status code
     *
     * @return bool
     */
    private function empty(): bool
    {
        $responseCode = ResponseDepot::getStatus();
        if (isset($this->noContentEligible[$responseCode])) {
            ResponseDepot::setContent('');
            ResponseDepot::setHeader('Content-Type', '', false);
            ResponseDepot::setHeader('Content-Length', '', false);
            ini_set('default_mimetype', '');
            if ($responseCode === 304) {
                foreach (
                    [
                        'Allow',
                        'Content-Encoding',
                        'Content-Language',
                        'Content-MD5',
                        'Last-Modified'
                    ] as $header
                ) {
                    ResponseDepot::setHeader($header, null, false);
                }
            }
            return true;
        }
        return false;
    }
}
