<?php


namespace AbmmHasan\WebFace\Support;


class HTTPResource
{
    public static $responseVersion = '1.1';

    public static $statusList = [
        // Official Response Series (defined by IANA)
        // 100 Series: Informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // 200 Series: Successful
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // 300 Series: Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // 400 Series: Client Error
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        // 500 Series: Server Error
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        // Custom Response Series (unofficial & project specific)
    ];

    public static $cache = [
        // Standard Control
        'must_revalidate' => true,
        'no_cache' => true,
        'no_store' => true,
        'no_transform' => true,
        'public' => false,
        'private' => false,
        'proxy_revalidate' => true,
        'max_age' => false,
        's_maxage' => false,
        // Control with Compatibility Issue (check mdn for details)
        'immutable' => true,
        'stale_while_revalidate' => false,
        'stale_if_error' => false,
        // Other Separate Directives
        'last_modified' => false,
        'vary' => false,
        'etag' => false
    ];
}