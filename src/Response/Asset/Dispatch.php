<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\Settings;
use Exception;

class Dispatch
{
    public function hello()
    {
        Prepare::contentAndCache();
        Prepare::cacheHeader();
        $flushable = URL::getMethod('main') !== 'HEAD';

    }


    /**
     * Prepare & Send all the headers
     *
     * @throws Exception
     */
    protected function headers()
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
}