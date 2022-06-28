<?php


namespace AbmmHasan\WebFace\Response\Asset;


use AbmmHasan\WebFace\Request\Asset\BaseRequest;
use AbmmHasan\WebFace\Request\Asset\Headers;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\Settings;
use ArrayObject;
use Exception;
use JsonSerializable;

/**
 * Class BaseResponse
 * @package Inspect\Core\BaseRequest
 */
abstract class BaseResponse extends BaseRequest
{
    /**
     * Checks if eligible header found
     *
     * This is still experimental
     *
     * @param string $type
     * @param bool $all
     * @return mixed
     */
    private function getTypeHeader(string $type, bool $all = false): mixed
    {
        $heads = [
            'html' => ['text/html', 'application/xhtml+xml'],
            'txt' => ['text/plain'],
            'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'],
            'css' => ['text/css'],
            'json' => ['application/json', 'application/x-json'],
            'jsonld' => ['application/ld+json'],
            'xml' => ['text/xml', 'application/xml', 'application/x-xml'],
            'rdf' => ['application/rdf+xml'],
            'atom' => ['application/atom+xml'],
            'rss' => ['application/rss+xml'],
            'form' => ['application/x-www-form-urlencoded'],
        ];
        return $all ? ($heads[$type] ?? []) : ($heads[$type][0] ?? null);
    }

    /**
     * Prepare & Send all the headers (Cookies included)
     *
     * @throws Exception
     */
    protected function sendHeaders()
    {
        // headers have already been sent
        if (headers_sent()) {
            return;
        }
        // Set Status Header
        $responseCode = ResponseDepot::getStatus();
        header(
            "HTTP/" . HTTPResource::$responseVersion . " {$responseCode} " . HTTPResource::$statusList[$responseCode],
            true,
            $responseCode
        );

        // headers
        foreach (ResponseDepot::getHeader() as $name => $values) {
            $replace = 0 === strcasecmp($name, 'Content-Type');
            header($name . ': ' . implode(',', $values), $replace, $responseCode);
        }

        header('X-Powered-By: WebFace', true, $responseCode);

        // Set Cookies
        if (!empty($responseCookies = ResponseDepot::getCookie())) {
            $expire = httpDate(date(DATE_ATOM, time() + (Settings::$cookieLifetime * 60)));
            $isSecure = Settings::$cookieIsSecure && URL::get('scheme') === 'https';
            foreach ($responseCookies as $name => $cookie) {
                if (!$isSecure && $cookie['samesite'] === 'None') {
                    header_remove();
                    throw new Exception("Cookie ($name) with 'SameSite=None' attribute, must also specify the Secure attribute");
                }
                header('Set-Cookie: ' . rawurlencode($name) . '=' . rawurlencode($cookie['value'])
                    . '; Expires=' . $expire
                    . (empty($cookie['maxage']) ? '' : '; Max-Age=' . $cookie['maxage'])
                    . (empty(Settings::$cookieDomain) ? '' : '; Domain=' . Settings::$cookieDomain)
                    . (empty(Settings::$cookiePath) ? '' : '; Path=' . Settings::$cookiePath)
                    . '; SameSite=' . $cookie['samesite']
                    . (!$isSecure ? '' : '; Secure')
                    . (!Settings::$cookieHttpOnly ? '' : '; HttpOnly'), false);
            }
        }
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
        if (ResponseDepot::getStatus() !== 304 && URL::getMethod('converted') === 'GET') {
            $cacheHeaders = ResponseDepot::getCache();
            $lastModified = $cacheHeaders['Last-Modified'] ?? null;
            $modifiedSince = Headers::responseDependency('if_modified_since');
            if (!empty($noneMatch = Headers::responseDependency('if_none_match'))) {
                $notModified = in_array($cacheHeaders['ETag'] ?? '*', $noneMatch) || in_array('*', $noneMatch);
            }
            if ($modifiedSince && $lastModified) {
                $notModified = strtotime($modifiedSince) >= strtotime($lastModified) &&
                    (empty($noneMatch) || $notModified);
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
    private function emptyResponse(): bool
    {
        $responseCode = ResponseDepot::getStatus();
        if (($responseCode >= 100 && $responseCode < 200) || in_array($responseCode, [204, 304])) {
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
                    ResponseDepot::setHeader($header, '', false);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Preparing standard response
     *
     * RFC 2616
     *
     * @return void
     * @throws Exception
     */
    private function prepare(): void
    {
        $isUnmodified = $this->notModified();
        $isEmpty = $this->emptyResponse();
        if ($isEmpty || $isUnmodified) {
            return;
        }
        $setHeaders = ResponseDepot::getHeader();
        // Content-type based on the Request
        if (!isset($setHeaders['Content-Type']) && !empty($eligible = ResponseDepot::$contentType)) {
            $parsed = $this->getTypeHeader($eligible, true);
            $fromRequest = Headers::content('type') ?? $parsed[0];
            if (in_array($fromRequest, $parsed)) {
                ResponseDepot::setHeader('Content-Type', $fromRequest, false);
            }
        }
        // Fix Content-Type
        $responseCharset = ResponseDepot::$charset ?? Headers::content('charset') ?? 'UTF-8';
        if (!isset($setHeaders['Content-Type'])) {
            ResponseDepot::setHeader('Content-Type', 'text/html; charset=' . $responseCharset, false);
        } elseif (0 === stripos($setHeaders['Content-Type'][0], 'text/') && false === stripos(
                $setHeaders['Content-Type'][0],
                'charset'
            )) {
            ResponseDepot::setHeader(
                'Content-Type',
                $setHeaders['Content-Type'][0] . '; charset=' . $responseCharset,
                false
            );
        }

        // Fix Content-Length
        if (isset($setHeaders['Transfer-Encoding'])) {
            ResponseDepot::setHeader('Content-Length', '', false);
        }
    }

    /**
     * Check if the response is intermediary cacheable
     *
     * RFC 7231
     *
     * @param $cacheVariables
     * @return bool
     */
    public function isSharedCacheable($cacheVariables): bool
    {
        if (!in_array(ResponseDepot::getStatus(), [200, 203, 300, 301, 302, 404, 410])) {
            return false;
        }

        if (isset($cacheVariables['control']['no_store']) ||
            (isset($cacheVariables['control']['visibility']) &&
                $cacheVariables['control']['visibility'] == 'private')) {
            return false;
        }

        if (isset($cacheVariables['Last-Modified'])) {
            return true;
        }

        $maxAge = $cacheVariables['control']['s-maxage'] ?? $cacheVariables['control']['max-age'] ?? null;

        return ($maxAge ?? null) > 0;
    }

    /**
     * Cache Control Calculator
     *
     * This will set the Control directives to desired standard
     *
     * @return mixed|string[]
     */
    private function computeCacheControl($cacheVariables): mixed
    {
        if (empty($cacheVariables['control'])) {
            if (isset($cacheVariables['Last-Modified'])) {
                return ['private', 'must-revalidate'];
            }
            return ['no-cache', 'private'];
        }

        if (!$this->isSharedCacheable($cacheVariables)) {
            unset($cacheVariables['control']['s-maxage']);
        }

        if (isset($cacheVariables['control']['s-maxage'])) {
            $cacheVariables['control']['visibility'] = 'public';
        }

        if (isset($cacheVariables['control']['visibility'])) {
            return $cacheVariables['control'];
        }
        $cacheVariables['control']['visibility'] = 'private';
        return $cacheVariables['control'];
    }

    /**
     * Preparing cache headers
     *
     * RFC 7231, RFC 7234, RFC 8674
     *
     * @return void
     * @throws Exception
     */
    private function prepareCacheHeader()
    {
        $cacheVariables = ResponseDepot::getCache();
        if (in_array(URL::getMethod('converted'), ['GET', 'POST']) &&
            in_array(ResponseDepot::getStatus(), [200, 203, 204, 206, 300, 301, 404, 405, 410, 414, 501])) {
            $control = array_values($this->computeCacheControl($cacheVariables));
            if (!empty($control)) {
                ResponseDepot::setHeader('Cache-Control', implode(',', $control));
            }
        }
        unset($cacheVariables['control']);
        if (isset($cacheVariables['special'])) {
            foreach ($cacheVariables['special'] as $label => $value) {
                ResponseDepot::setHeader($label, implode(',', $value));
            }
            unset($cacheVariables['special']);
        }
        if (!empty($cacheVariables)) {
            foreach ($cacheVariables as $label => $value) {
                ResponseDepot::setHeader($label, $value);
            }
        }
    }

    /**
     * Get response
     *
     * @param $content
     * @return ArrayObject|false|mixed|string
     */
    private function contentParser($content): mixed
    {
        $shouldBeJSON = ResponseDepot::$contentType == 'json' || $content instanceof JsonSerializable;
        $isArray = $content instanceof ArrayObject || is_array($content);
        if ($shouldBeJSON || $isArray) {
            ResponseDepot::setHeader('Content-Type', 'application/json', false);
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return $content;
    }

    /**
     *
     * @return false|int|null
     */
    private function handleContent(): bool|int|null
    {
        $length = null;
        if (!empty(ResponseDepot::getContent())) {
            ob_start();
            ob_start("ob_gzhandler");
            echo $this->contentParser(ResponseDepot::getContent());
            ob_get_flush();
            $length = ob_get_length();
            ob_get_flush();
        }
        return $length;
    }

    /**
     * Sends output
     *
     * @throws Exception
     */
    public function helloWorld($targetFlashLevel = 0)
    {
        $this->prepareCacheHeader();
        $this->prepare();

        $flushable = $this->originalMethod !== 'HEAD';

        $length = $this->handleContent();

        if (!$flushable && !is_null($length)) {
            ResponseDepot::setHeader('Content-Length', $length, false);
        }

        $this->sendHeaders();

        if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $status = ob_get_status(true);
            $level = count($status);
            $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flushable ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);

            while (
                $level-- > $targetFlashLevel &&
                ($s = $status[$level]) &&
                (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])
            ) {
                if ($flushable) {
                    ob_end_flush();
                } else {
                    ob_end_clean();
                }
            }
        }
    }
}
