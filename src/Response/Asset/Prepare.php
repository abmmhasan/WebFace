<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\WebFace\Request\Asset\Headers;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Response\Response;
use AbmmHasan\WebFace\Router\Asset\Settings;
use Exception;

final class Prepare
{
    use Single;

    private Repository $repository;
    private Response $response;

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

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->repository = Repository::instance();
        $this->response = Response::instance();
    }

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
        $cache = $this->repository->getCache();
        if (isset($this->applicableStatus[$this->repository->getStatus()]) &&
            (URL::instance()->getMethod('converted') === 'GET' ||
                (URL::instance()->getMethod('converted') === 'POST' && $this->repository->getHeader('Content-Location'))
            )
        ) {
            $control = array_values($this->computeCacheControl($cache));
            if (!empty($control)) {
                $this->response->header('Cache-Control', implode(',', $control), false);
            }
        }
        unset($cache['Cache-Control']);
        if (!empty($cache)) {
            foreach ($cache as $label => $value) {
                $this->response->header($label, implode(',', (array)$value), false);
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
        $content = $this->contentParser($this->repository->getContent());

        // check & set content type
        $contentType = $this->repository->getHeader('Content-Type');
        if ($contentType === null) {
//            $applicable = array_intersect(ResponseDepot::$applicableFormat, (array)Headers::instance()->content('type'));
            if (!empty($applicable)) {
                $this->response->header('Content-Type', current($applicable), false);
            } else {
                $charset = $this->repository->getCharset() ?? 'UTF-8';
                $this->response->header('Content-Type', 'text/html; charset=' . $charset, false);
            }
        }

        if ($this->repository->getStatus() < 300 &&
            (empty($contentType) || !array_intersect(
                    array_merge($contentType, ['*/*']),
                    Headers::instance()->accept('Accept')
                ))
        ) {
            $this->response
                ->status(406)
                ->fail();
            $this->repository->setRawContent(
                $this->contentParser($this->repository->getContent())
            );
            return;
        }

        $this->calculateEtag($content);
        $this->shouldMarkAsModified();
        if ($this->empty()) {
            return;
        }

        $this->repository->setRawContent($content);

        // Fix Content-Length; ToDo: WIP
//        if (isset($setHeaders['Transfer-Encoding'])) {
//            $this->response->header('Content-Length', '', false);
//        }
    }

    /**
     * Calculate Etag
     *
     * @param string|null $content
     * @return void
     */
    private function calculateEtag(string $content = null): void
    {
        match ($this->repository->getCache('ETag')) {
            'W/"auto"' => $this->repository->setCache('ETag',
                'W/"' . hash(
                    Settings::$weakEtagMethod,
                    $content ?? $this->repository->getContent()
                ) . '"'
            ),
            '"auto"' => $this->repository->setCache('ETag',
                '"' . hash(
                    Settings::$etagMethod,
                    $content ?? (json_encode($this->repository->getHeader()) . $this->repository->getContent())
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
        if ($this->repository->getType() === 'json') {
            if (!is_array($content)) {
                throw new Exception('Host response is not JSON compatible!');
            }
            $this->response->header('Content-Type', $this->repository->getMime(), false);
            $content['message'] ??= HTTPResource::$statusList[$this->repository->getStatus()][1];
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?:
                throw new Exception('Unable to parse response as JSON(' . json_last_error_msg() . ')');
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
            !isset($this->applicableSharedStatus[$this->repository->getStatus()]) ||
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
    private function shouldMarkAsModified(): bool
    {
        $notModified = false;
        if ($this->repository->getStatus() !== 304 && URL::instance()->getMethod('converted') === 'GET') {
            $cacheHeaders = $this->repository->getCache();
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
                $this->repository->setStatus(304);
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
        $responseCode = $this->repository->getStatus();
        if (isset($this->noContentEligible[$responseCode])) {
            $this->repository->setRawContent();
            $this->response->header('Content-Type', '', false);
            $this->response->header('Content-Length', '', false);
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
                    $this->response->header($header, null, false);
                }
            }
            return true;
        }
        return false;
    }
}
