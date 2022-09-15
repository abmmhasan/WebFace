<?php

namespace AbmmHasan\WebFace\Request\Asset;

use AbmmHasan\Bucket\Functional\Arrject;
use AbmmHasan\WebFace\Common\StaticSingleInstance;
use AbmmHasan\WebFace\Common\Value;

final class Headers
{
    private Arrject $headers;
    private Arrject $accept;
    private Arrject $content;
    private Arrject $dependency;

    use Value, StaticSingleInstance;

    /**
     * Get all HTTP headers
     *
     * @param string|null $key
     * @return mixed
     */
    public function all(string $key = null): mixed
    {
        if (!isset($this->headers)) {
            $headerVar = [];
            $server = CommonAsset::instance()->server();
            foreach ($server as $item => $value) {
                if (str_starts_with($item, 'HTTP_')) {
                    $item = ucwords(strtolower(strtr(substr($item, 5), '_', '-')), "-");
                    $headerVar[$item] = $value;
                } elseif (in_array($item, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $item = ucwords(strtolower(strtr($item, '_', '-')), "-");
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
            $this->headers = new Arrject($headerVar);
        }
        return $this->find($this->headers, $key);
    }

    /**
     * Get parsed Accept headers
     *
     * @param string|null $key
     * @return mixed
     */
    public function accept(string $key = null): mixed
    {
        if (!isset($this->accept)) {
            $this->all();
            $parsed = [];
            foreach (['Accept', 'Accept-Charset', 'Accept-Encoding', 'Accept-Language'] as $accept) {
                if (isset($this->headers[$accept])) {
                    $parsed[$accept] = $this->parseAcceptHeader($this->headers[$accept]);
                }
            }
            $this->accept = new Arrject($parsed);
        }
        return $this->find($this->accept, $key);
    }

    /**
     * Get parsed content headers
     *
     * @param string|null $key
     * @return mixed
     */
    public function content(string $key = null): mixed
    {
        if (!isset($this->content)) {
            $this->all();
            $parts = explode(';', strtolower($this->headers['Content-Type'] ?? ''));
            $type = array_shift($parts);
            $charset = null;
            if ($parts) {
                foreach ($parts as $part) {
                    $part = strtr($part, ' ', '');
                    if (str_starts_with($part, 'charset=')) {
                        $charset = substr($part, 8);
                        break;
                    }
                }
            }
            $this->content = new Arrject([
                'parts' => $parts,
                'type' => empty($type) ? null : $type,
                'charset' => $charset,
                'length' => $this->headers['Content-Length'] ?? 0,
                'md5' => strtolower($this->headers['Content-Md5'] ?? '')
            ]);
        }
        return $this->find($this->content, $key);
    }

    /**
     * Get response dependency
     *
     * @param string|null $key
     * @return mixed
     */
    public function responseDependency(string $key = null): mixed
    {
        if (!isset($this->dependency)) {
            $this->all();
            $asset = [
                'if_match' => preg_split(
                    '/\s*,\s*/',
                    $this->headers['If-Match'] ?? '',
                    0,
                    PREG_SPLIT_NO_EMPTY
                ),
                'if_none_match' => preg_split(
                    '/\s*,\s*/',
                    $this->headers['If-None-Match'] ?? '',
                    0,
                    PREG_SPLIT_NO_EMPTY
                ),
                'if_modified_since' => !empty($this->headers['If-Modified-Since']) ?
                    date(DATE_ATOM, strtotime($this->headers['If-Modified-Since'])) : null,
                'if_unmodified_since' => !empty($this->headers['If-Unmodified-Since']) ?
                    date(DATE_ATOM, strtotime($this->headers['If-Unmodified-Since'])) : null,
                'range' => null,
                'prefer_safe' => ($this->headers['Prefer'] ?? '') === 'safe' && URL::instance()->get('scheme') === 'https'
            ];
            if (isset($this->headers['Range'])) {
                $range = explode('=', strtr($this->headers['Range'], " ", ""), 2);
                if (count($range) === 2) {
                    $asset['range'] = [
                        'unit' => $range[0],
                        'span' => str_getcsv($range[1])
                    ];
                }
            }
            $this->dependency = new Arrject($asset);
        }
        return $this->find($this->dependency, $key);
    }

    /**
     * Accept header parser
     *
     * @param string $content
     * @return array
     */
    private function parseAcceptHeader(string $content): array
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
                'wild' => $this->compareWildcard(explode('/', $asset)),
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

    private function compareWildcard($types): bool|int
    {
        return count($types) === 1 ? 0 : ($types[0] === '*') - ($types[1] === '*');
    }
}
