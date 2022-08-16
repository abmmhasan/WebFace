<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\Settings;
use Exception;

final class Dispatch
{
    /**
     * Dispatch response
     *
     * @return void
     * @throws Exception
     */
    public function hello(): void
    {
        Prepare::contentAndCache();
        Prepare::cacheHeader();
        $flushable = URL::getMethod('main') !== 'HEAD';
        $length = $this->content();
        if (!$flushable && !is_null($length)) {
            ResponseDepot::setHeader('Content-Length', $length, false);
        }
        $this->headers();
        $this->flushContent($flushable);
    }

    /**
     * Prepare & Send all the headers
     *
     * @throws Exception
     */
    protected function headers(): void
    {
        // headers have already been sent
        if (headers_sent()) {
            return;
        }

        // Set Status Header
        $responseCode = ResponseDepot::getStatus();
        header(
            "HTTP/" . HTTPResource::$responseVersion . " $responseCode " . HTTPResource::$statusList[$responseCode],
            true,
            $responseCode
        );

        $sendCookie = false;

        // headers
        foreach (ResponseDepot::getHeader() as $name => $values) {
            $values = implode(',', $values);
            if ($replace = (0 === strcasecmp($name, 'Content-Type'))) {
                $sendCookie = str_starts_with($values, 'Content-Type: text/');
            }
            header("$name: $values", $replace);
        }

        header('X-Powered-By: WebFace');

        if ($responseCode >= 400) {
            header('Connection: close');
        }

        // Set Cookies (if text type response)
        if ($sendCookie && !!($responseCookies = ResponseDepot::getCookie())) {
            $expire = time() + (Settings::$cookieLifetime * 60);
            $domain = Settings::$cookieDomain ?? URL::get('host');
            $secure = Settings::$cookieIsSecure && URL::get('scheme') === 'https';
            foreach ($responseCookies as $name => [$value, $options]) {
                setcookie($name, $value, [
                    'expires' => $options['expires'] ?? $expire,
                    'path' => $options['path'] ?? Settings::$cookiePath,
                    'domain' => $options['domain'] ?? $domain,
                    'secure' => $secure,
                    'httponly' => Settings::$cookieHttpOnly,
                    'samesite' => $options['samesite'] ?? Settings::$cookieSameSite
                ]);
            }
        }
    }

    /**
     * Output body
     *
     * @param bool $flushable
     * @param int $targetFlushLevel
     * @return void
     */
    private function flushContent(bool $flushable, int $targetFlushLevel = 0): void
    {
        if (!in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $status = ob_get_status(true);
            $level = count($status);
            $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flushable ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);
            $flushFunction = $flushable ? 'ob_end_flush' : 'ob_end_clean';
            while (
                $level-- > $targetFlushLevel &&
                ($s = $status[$level]) &&
                (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])
            ) {
                $flushFunction();
            }
        }
    }

    /**
     * Content output into buffer
     *
     * @return bool|int|null
     */
    private function content(): bool|int|null
    {
        $length = null;
        if (!empty(ResponseDepot::getContent())) {
            ob_start();
            ob_start("ob_gzhandler");
            echo ResponseDepot::getContent();
            ob_get_flush();
            $length = ob_get_length();
            ob_get_flush();
        }
        return $length;
    }
}