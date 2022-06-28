<?php

namespace AbmmHasan\WebFace\Request\Asset;

use AbmmHasan\Bucket\Functional\Arrject;

final class Headers extends Utility
{
    private static Arrject $headers;
    private static Arrject $accept;
    private static Arrject $content;
    private static Arrject $dependency;

    /**
     * Get all HTTP headers
     *
     * @param string|null $key
     * @return mixed
     */
    public static function all(string $key = null): mixed
    {
        if (!isset(self::$headers)) {
            $headerVar = [];
            $server = CommonAsset::server();
            foreach ($server as $item => $value) {
                if (str_starts_with($item, 'HTTP_')) {
                    $item = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($item, 5)))));
                    $headerVar[$item] = $value;
                } elseif (in_array($item, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $item = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $item))));
                    $headerVar[$item] = $value;
                }
            }

            if (isset($server['PHP_AUTH_USER'])) {
                $headerVar['PHP_AUTH_USER'] = $server['PHP_AUTH_USER'];
                $headerVar['PHP_AUTH_PW'] = $server['PHP_AUTH_PW'] ?? '';
            } else {
                $authorizationHeader = null;
                if (isset($server['HTTP_AUTHORIZATION'])) {
                    $authorizationHeader = $server['HTTP_AUTHORIZATION'];
                } elseif (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                    $authorizationHeader = $server['REDIRECT_HTTP_AUTHORIZATION'];
                }

                if (null !== $authorizationHeader) {
                    if (0 === stripos($authorizationHeader, 'basic ')) {
                        // Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
                        $exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
                        if (2 == count($exploded)) {
                            list($headerVar['PHP_AUTH_USER'], $headerVar['PHP_AUTH_PW']) = $exploded;
                        }
                    } elseif (empty($server['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader, 'digest '))) {
                        // In some circumstances PHP_AUTH_DIGEST needs to be set
                        $headerVar['PHP_AUTH_DIGEST'] = $authorizationHeader;
                        $server['PHP_AUTH_DIGEST'] = $authorizationHeader;
                    } elseif (0 === stripos($authorizationHeader, 'bearer ')) {
                        $headerVar['Authorization'] = $authorizationHeader;
                    }
                }
            }
            if (!isset($headerVar['Authorization'])) {
                // PHP_AUTH_USER/PHP_AUTH_PW
                if (isset($headerVar['PHP_AUTH_USER'])) {
                    $headerVar['Authorization'] = 'Basic ' . base64_encode(
                            $headerVar['PHP_AUTH_USER'] . ':' . $headerVar['PHP_AUTH_PW']
                        );
                } elseif (isset($headerVar['PHP_AUTH_DIGEST'])) {
                    $headerVar['Authorization'] = $headerVar['PHP_AUTH_DIGEST'];
                }
            }
            self::$headers = new Arrject($headerVar);
        }
        return self::getValue(self::$headers, $key);
    }

    /**
     * Get parsed Accept headers
     *
     * @param string|null $key
     * @return mixed
     */
    public static function accept(string $key = null): mixed
    {
        if (!isset(self::$accept)) {
            self::all();
            $parsed = [];
            foreach (['Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language'] as $accept) {
                if (isset(self::$headers[$accept])) {
                    $parsed[$accept] = self::parseAcceptHeader(self::$headers[$accept]);
                }
            }
            self::$accept = new Arrject($parsed);
        }
        return self::getValue(self::$accept, $key);
    }

    /**
     * Get parsed content headers
     *
     * @param string|null $key
     * @return mixed
     */
    public static function content(string $key = null): mixed
    {
        if (!isset(self::$content)) {
            self::all();
            $parts = explode(';', strtolower(self::$headers['Content-Type'] ?? ''));
            $type = array_shift($parts);
            $charset = null;
            if ($parts) {
                foreach ($parts as $part) {
                    $part = str_replace(' ', '', $part);
                    if (str_starts_with($part, 'charset=')) {
                        $charset = substr($part, 8);
                        break;
                    }
                }
            }
            self::$content = new Arrject([
                'parts' => $parts,
                'type' => empty($type) ? null : $type,
                'charset' => $charset,
                'length' => self::$headers['Content-Length'] ?? 0,
                'md5' => strtolower(self::$headers['Content-Md5'] ?? '')
            ]);
        }
        return self::getValue(self::$content, $key);
    }

    /**
     * Get response dependency
     *
     * @param string|null $key
     * @return mixed
     */
    public static function responseDependency(string $key = null): mixed
    {
        if (!isset(self::$dependency)) {
            self::all();
            $asset = [
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
                'range' => null,
                'prefer_safe' => (self::$headers['Prefer'] ?? '') === 'safe' && URL::get()->scheme === 'https'
            ];
            if (isset(self::$headers['Range'])) {
                $range = explode('=', str_replace(" ", "", self::$headers['Range']), 2);
                if (count($range) === 2) {
                    $asset['range'] = [
                        'unit' => $range[0],
                        'span' => str_getcsv($range[1])
                    ];
                }
            }
            self::$dependency = new Arrject($asset);
        }
        return self::getValue(self::$dependency, $key);
    }

    /**
     * Accept header parser
     *
     * @param string $content
     * @return array
     */
    private static function parseAcceptHeader(string $content): array
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

    private static function compareWildcard($types): bool|int
    {
        return count($types) === 1 ? 0 : ($types[0] === '*') - ($types[1] === '*');
    }
}
