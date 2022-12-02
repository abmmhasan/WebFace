<?php


namespace AbmmHasan\WebFace\Response\Asset;


final class HTTPResource
{
    public static string $responseVersion = '1.1';

    /**
     * HTTP Status list
     *
     * https://kinsta.com/blog/http-status-codes
     *
     * @var array|string[]
     */
    public static array $statusList = [

        // Official Response Series (defined by IANA)

        // 100 Series: Informational
        100 => ['Continue', 'Continue'],
        101 => ['Switching Protocols', 'Switching Protocols'],
        102 => ['Processing', 'Processing'],
        103 => ['Early Hints', 'Early Hints'],

        // 200 Series: Successful
        200 => ['OK', 'Everything is OK'],
        201 => ['Created', 'Request fulfilled & new resource created'],
        202 => ['Accepted', 'Request accepted & is being processed'],
        203 => ['Non-Authoritative Information', 'Response modified by proxy'],
        204 => ['No Content', ''],
        205 => ['Reset Content', ''],
        206 => ['Partial Content', 'Partial content'], // content that uses 'range' header
        207 => ['Multi-Status', ''],
        208 => ['Already Reported', ''],
        226 => ['IM Used', ''],

        // 300 Series: Redirection
        300 => ['Multiple Choices', 'Multiple possible resource choices'],
        301 => ['Moved Permanently', 'The requested resource has been moved permanently'],
        302 => ['Found', 'The requested resource has moved, but was found'],
        303 => ['See Other', ''],
        304 => ['Not Modified', 'The requested resource has not been modified since the last time you accessed it'],
        305 => ['Use Proxy', ''],
        306 => ['Reserved(Unused)', ''],
        307 => ['Temporary Redirect', ''],
        308 => ['Permanent Redirect', ''],

        // 400 Series: Client Error
        400 => ['Bad Request', 'Unable to respond due to client error'],
        401 => ['Unauthorized', 'Authorization Required'],
        402 => ['Payment Required', 'Issue detected within your payment'],
        403 => ['Forbidden', 'Access to that resource is forbidden'],
        404 => ['Not Found', 'The requested resource was not found'],
        405 => ['Method Not Allowed', ''],
        406 => ['Not Acceptable', 'Requested resource can\'t be delivered'],
        407 => ['Proxy Authentication Required', 'The in-use proxy server requires authentication'],
        408 => ['Request Timeout', 'The server timed out waiting for the rest of the request from the browser'],
        409 => ['Conflict', 'Unable to fulfill the request due to resource conflict'],
        410 => ['Gone', 'The requested resource is gone and won’t be coming back'],
        411 => ['Length Required', 'Unspecified length'],
        412 => ['Precondition Failed', 'Requested condition doesn\'t meet the server requirement'],
        413 => ['Request Entity Too Large', 'Request body is larger than expected'],
        414 => ['Request-URI Too Long', 'The URI(encoded) is too large to process'],
        415 => ['Unsupported Media Type', 'Provided media type is invalid!'],
        416 => ['Range Not Satisfiable', ''],
        417 => ['Expectation Failed', 'Requirement doesn\'t meet as provided by the client'],
        421 => ['Misdirected Request', ''],
        422 => ['Unprocessable Entity', 'Semantic error(s) detected, processing failed'],
        423 => ['Locked', ''],
        424 => ['Failed Dependency', ''],
        425 => ['Too Early', 'Possible replayed request, processing failed'],
        426 => ['Upgrade Required', 'Upgrade header field detected, different protocol switching required'],
        428 => ['Precondition Required', 'Condition must be specified for further processing'],
        429 => ['Too Many Requests', 'The rate of requests is too high'],
        431 => ['Request Header Fields Too Large', 'Size for, one of the header field or all of them collectively, exceeds threshold'],

        // 500 Series: Server Error
        500 => ['Internal Server Error', 'There was an error on the server and the request could not be completed'],
        501 => ['Not Implemented', 'The functionality required to fulfill the request, is not supported'],
        502 => ['Bad Gateway', 'Invalid response detected within servers'],
        503 => ['Service Unavailable', 'The server is unavailable to handle this request right now'],
        504 => ['Gateway Timeout', 'The server, acting as a gateway, timed out waiting for another server to respond'],
        505 => ['HTTP Version Not Supported', 'HTTP version not supported, should be 1.1'],
        506 => ['Variant Also Negotiates', ''],
        507 => ['Insufficient Storage', ''],
        508 => ['Loop Detected', ''],
        510 => ['Not Extended', ''],
        511 => ['Network Authentication Required', ''],

        // Custom Response Series (unofficial & project specific)
        509 => ['Bandwidth Limit Exceeded', ''],
    ];

    /**
     * Cache-Control directives
     *
     * @var array
     */
    public static array $cacheControl = [
        // Standard Control
        'max-age' => false,
        'must-revalidate' => true,
        'private' => true,
        'public' => true,
        's-maxage' => false,
        'proxy-revalidate' => true,
        'no-cache' => true,
        'no-store' => true,
        'no-transform' => true,
        'must-understand' => true,

        // Control with Compatibility Issue (check mdn for details)
        'immutable' => true,
        'stale-while-revalidate' => false,
        'stale-if-error' => false,
    ];

    public static array $conditionalCache = [
        // Other Separate Directives
        'Last-Modified' => false,
        'Vary' => false,
        'ETag' => false
    ];
}
