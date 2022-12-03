<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Response\Response;
use AbmmHasan\WebFace\Router\Asset\Settings;
use Exception;

final class Dispatch
{

    private Response $response;
    private Repository $repository;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->response = Response::instance();
        $this->repository = Repository::instance();
    }

    /**
     * Dispatch response
     *
     * @return void
     * @throws Exception
     */
    public function hello(): void
    {
        $prepare = Prepare::instance();
        $prepare->manageContentAndHeader();
        $prepare->cacheHeader();
        $flushable = URL::instance()->getMethod('main') !== 'HEAD';
        $length = $this->content();
        if (!$flushable && !is_null($length)) {
            $this->response->header('Content-Length', $length, false);
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
        $responseCode = $this->repository->getStatus();
        header(
            "HTTP/" . HTTPResource::$responseVersion .
            " $responseCode " . HTTPResource::$statusList[$responseCode][0],
            true,
            $responseCode
        );

        $sendCookie = false;

        // headers
        foreach ($this->repository->getHeader() as $name => $values) {
            $values = implode(',', $values);
            if ($replace = (0 === strcasecmp($name, 'Content-Type'))) {
                $sendCookie = str_starts_with($values, 'Content-Type: text/');
            }
            header("$name: $values", $replace);
        }

        header('X-Powered-By: ' . trim(Settings::$poweredBy));

        if ($responseCode === 408 || $responseCode >= 500) {
            header('Connection: close');
        }

        // Set Cookies (if text type response)
        if ($sendCookie && !!($responseCookies = $this->repository->getCookie())) {
            $expire = time() + (Settings::$cookieLifetime * 60);
            $url = URL::instance();
            $domain = Settings::$cookieDomain ?? $url->get('host');
            $secure = Settings::$cookieIsSecure && $url->get('scheme') === 'https';
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
        if (!empty($this->repository->getContent())) {
            ob_start();
            ob_start("ob_gzhandler");
            echo $this->repository->getContent();
            ob_get_flush();
            $length = ob_get_length();
            ob_get_flush();
        }
        return $length;
    }
}
