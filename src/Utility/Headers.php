<?php

namespace AbmmHasan\WebFace\Utility;

final class Headers
{
    private static $headers;
    private static $accept;
    private static $content;
    private static $dependency;

    /**
     * Get all HTTP headers
     *
     * @return Arrject
     */
    public static function all()
    {
        if (self::$headers) {
            return self::$headers;
        }

        $headerVar = [];
        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headerVar[$key] = $value;
            } elseif (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headerVar[$key] = $value;
            }
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $headerVar['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'];
            $headerVar['PHP_AUTH_PW'] = $_SERVER['PHP_AUTH_PW'] ?? '';
        } else {
            $authorizationHeader = null;
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authorizationHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }

            if (null !== $authorizationHeader) {
                if (0 === stripos($authorizationHeader, 'basic ')) {
                    // Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
                    $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
                    if (2 == count($exploded)) {
                        list($headerVar['PHP_AUTH_USER'], $headerVar['PHP_AUTH_PW']) = $exploded;
                    }
                } elseif (empty($_SERVER['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader, 'digest '))) {
                    // In some circumstances PHP_AUTH_DIGEST needs to be set
                    $headerVar['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    $_SERVER['PHP_AUTH_DIGEST'] = $authorizationHeader;
                } elseif (0 === stripos($authorizationHeader, 'bearer ')) {
                    $headerVar['Authorization'] = $authorizationHeader;
                }
            }
        }
        if (isset($headerVar['Authorization'])) {
            return self::$headers = new Arrject($headerVar);
        }

        // PHP_AUTH_USER/PHP_AUTH_PW
        if (isset($headerVar['PHP_AUTH_USER'])) {
            $headerVar['Authorization'] = 'Basic ' . base64_encode(
                    $headerVar['PHP_AUTH_USER'] . ':' . $headerVar['PHP_AUTH_PW']
                );
        } elseif (isset($headerVar['PHP_AUTH_DIGEST'])) {
            $headerVar['Authorization'] = $headerVar['PHP_AUTH_DIGEST'];
        }

        return self::$headers = new Arrject($headerVar);
    }

    /**
     * Get parsed accept headers
     *
     * @return Arrject
     */
    public static function accept()
    {
        if (self::$accept) {
            return self::$accept;
        }
        self::all();
        $parsed = [];
        foreach (['Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language'] as $accept) {
            if (isset(self::$headers[$accept])) {
                $parsed[$accept] = self::parseAcceptHeader(self::$headers[$accept]);
            }
        }
        return self::$accept = new Arrject($parsed);
    }

    /**
     * Get parsed content headers
     *
     * @return Arrject
     */
    public static function content()
    {
        if (self::$content) {
            return self::$content;
        }
        $parts = explode(';', strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE']));
        $type = array_shift($parts);
        $charset = null;
        if ($parts) {
            foreach ($parts as $part) {
                $part = str_replace(' ', '', $part);
                if (substr($part, 0, 8) == 'charset=') {
                    $charset = substr($part, 8);
                    break;
                }
            }
        }
        $length = $_SERVER['CONTENT_LENGTH'] ?? $_SERVER['HTTP_CONTENT_LENGTH'] ?? 0;
        $md5 = isset($_SERVER['HTTP_CONTENT_MD5']) ? strtolower($_SERVER['HTTP_CONTENT_MD5']) : null;
        return self::$content = new Arrject([
            'parts' => $parts,
            'type' => $type,
            'charset' => $charset,
            'length' => $length,
            'md5' => $md5
        ]);
    }

    public static function responseDependency()
    {
        if (self::$dependency) {
            return self::$dependency;
        }
        self::all();
        $url = URL::get();
        return self::$dependency = new Arrject([
            'if_match' => preg_split(
                '/\s*,\s*/',
                self::$headers['If-Match'] ?? null,
                null,
                PREG_SPLIT_NO_EMPTY
            ),
            'if_none_match' => preg_split(
                '/\s*,\s*/',
                self::$headers['If-None-Match'] ?? null,
                null,
                PREG_SPLIT_NO_EMPTY
            ),
            'if_modified_since' => !empty(self::$headers['If-Modified-Since']) ?
                date(DATE_ATOM, strtotime(self::$headers['If-Modified-Since'])) : null,
            'if_unmodified_since' => !empty(self::$headers['If-Unmodified-Since']) ?
                date(DATE_ATOM, strtotime(self::$headers['If-Unmodified-Since'])) : null,
            'prefer_safe' => (self::$headers['Prefer'] ?? '') === 'safe' && $url->scheme === 'https'
        ]);
    }

    private static function parseAcceptHeader($content)
    {
        $prepared = [];
        $parts = explode(',', $content);
        $count = count($parts);
        foreach ($parts as $index => $part) {
            if (empty($part)) {
                continue;
            }
            $params = explode(';', $part);
            $asset = trim(current($params));
            $prepared[$index] = [
                'sort' => $count - $index,
                'accept' => $asset,
                'wild' => self::compareWildcard(explode('/', $asset)),
                'q' => 1.0
            ];
            while (next($params)) {
                list($name, $value) = explode('=', current($params));
                $prepared[$index][trim($name)] = trim($value);
            }
        }
        usort(
            $prepared,
            function ($a, $b) {
                return [$b['q'], $b['wild'], $b['sort']] <=> [$a['q'], $a['wild'], $a['sort']];
            }
        );
        return array_column($prepared, 'accept');
    }

    private static function compareWildcard($types)
    {
        if (count($types) === 1) {
            return 0;
        }
        return ($types[0] === '*') - ($types[1] === '*');
    }
}