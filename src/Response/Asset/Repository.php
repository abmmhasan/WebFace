<?php

namespace AbmmHasan\WebFace\Response\Asset;

use AbmmHasan\Bucket\Array\Dotted;
use AbmmHasan\OOF\Fence\Single;
use Exception;
use InvalidArgumentException;

class Repository
{
    use Single;

    protected string $contentType = 'json';
    protected string $charset = 'UTF-8';
    protected int $code = 200;
    protected array $header = [];
    protected array $cookieHeader = [];
    protected mixed $content;
    protected array $cacheHeader = [
        'Vary' => ['Accept-Encoding']
    ];
    protected array $applicableFormat = [
        'html' => 'text/html',
        'json' => 'application/json',
        'file' => null
    ];

    public function __construct()
    {
        $this->content = [
            'status' => 'error',
            'message' => 'Unknown service error'
        ];
    }

    /**
     * Get charset
     *
     * @return int
     */
    public function getCharset(): int
    {
        return $this->charset;
    }

    /**
     * Set charset
     *
     * @param string $charset
     * @param bool $check
     */
    public function setCharset(string $charset, bool $check = true): void
    {
        if ($check && !in_array($charset, mb_list_encodings())) {
            throw new InvalidArgumentException("Invalid charset $charset!");
        }
        $this->charset = $charset;
    }

    /**
     * Get content type
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->contentType;
    }

    /**
     * Set content type
     *
     * @param string $contentType
     */
    public function setType(string $contentType): void
    {
        if (!isset($this->applicableFormat[$contentType])) {
            throw new InvalidArgumentException("Invalid content type $contentType!");
        }
        $this->contentType = $contentType;
    }

    /**
     * Get mime for set type
     *
     * @return string
     */
    public function getMime(): string
    {
        return $this->applicableFormat[$this->contentType];
    }

    /**
     * Set status code
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->code;
    }

    /**
     * Set status code
     *
     * @param int $code
     */
    public function setStatus(int $code): void
    {
        if (!isset(HTTPResource::$statusList[$code])) {
            throw new InvalidArgumentException("Invalid status code $code!");
        }
        $this->code = $code;
    }

    /**
     * Get header
     *
     * @param $label
     * @return array|mixed|string
     */
    public function getHeader($label = null): mixed
    {
        if ($label === null) {
            return $this->header;
        }
        return $this->header[$label] ?? null;
    }

    /**
     * Set header
     *
     * @param string $label
     * @param string|null $value
     * @param bool $append
     */
    public function setHeader(string $label, ?string $value = null, bool $append = true): void
    {
        $label = preg_replace('/[^a-zA-Z0-9-]/', '', $label);
        $label = ucwords($label, "-");
        $value = $value === null ? null : str_replace(["\r", "\n"], '', trim($value));

        $header = $this->header;
        if ($label === 'Content-Type' && isset($header['Content-Type'])) {
            $append = false;
        }
        if ($append && !empty($value)) {
            $header[$label][] = $value;
        } elseif (!$append && empty($value)) {
            unset($header[$label]);
        } elseif (!$append) {
            $header[$label] = [$value];
        }
        $this->header = $header;
    }

    /**
     * Get cache header
     *
     * @param string|null $label
     * @return array|mixed
     */
    public function getCache(?string $label = null): mixed
    {
        if (is_null($label)) {
            return $this->cacheHeader;
        }
        return $this->cacheHeader[$label] ?? [];
    }

    /**
     * Set cache header
     *
     * @param array|string $header
     * @param string|array|null $value
     */
    public function setCache(array|string $header, string|array|null $value = null): void
    {
        if (is_array($header)) {
            $this->cacheHeader = $header;
            return;
        }
        if ($value === null) {
            unset($this->cacheHeader[$header]);
        }
        $this->cacheHeader[$header] = $value;
    }

    /**
     * Get cookie
     *
     * @param string|null $label
     * @return mixed
     */
    public function getCookie(?string $label = null): mixed
    {
        if (is_null($label)) {
            return $this->cookieHeader;
        }
        return $this->cookieHeader[$label] ?? [];
    }

    /**
     * Set cookie
     *
     * @param array|string $label
     * @param string $value
     * @param array|null $options
     */
    public function setCookie(array|string $label, string $value, array $options = null): void
    {
        $this->cookieHeader[$label] = [$value, $options];
    }

    /**
     * Set content
     *
     * @param string $status
     * @param string|null $message
     * @param mixed|null $data
     * @param mixed ...$additional
     */
    public function setContent(
        string $status,
        string $message = null,
        mixed  $data = null,
        array  $additional = []
    ): void
    {
        $this->content = [
                'status' => $status,
                'message' => $message,
                'data' => $data
            ] + $additional;
    }

    /**
     * Set child(s), in attribute via dotted notation
     *
     * @param array|string $keys
     * @param mixed|null $value
     * @param string $contentKey
     * @throws Exception
     */
    public function setContentElement(array|string $keys, mixed $value = null, string $contentKey = 'data'): void
    {
        if (empty($this->content)) {
            throw new Exception('Content not initiated yet!');
        }
        if ($contentKey === 'status' || $contentKey === 'message') {
            throw new Exception('Status or message can\'t be set anymore!');
        }
        Dotted::set($this->content[$contentKey], $keys, $value);
    }

    /**
     * Set raw content (no predefined format)
     *
     * Caution: don't use this (will break things)
     *
     * @param mixed $content
     * @return void
     */
    public function setRawContent(mixed $content = null): void
    {
        $this->content = $content;
    }

    /**
     * Get content
     *
     * @param array|string|null $keys
     * @return array|mixed|string|null
     */
    public function getContent(array|string $keys = null): mixed
    {
        if ($keys === null || empty($this->content)) {
            return $this->content;
        }
        return Dotted::get($this->content, $keys);
    }
}
