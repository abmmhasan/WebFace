<?php


namespace AbmmHasan\WebFace;


use AbmmHasan\WebFace\Base\BaseResponse;
use AbmmHasan\WebFace\Support\ResponseDepot;
use AbmmHasan\WebFace\Utility\URL;
use Exception;

/**
 * @method static to(string $url, array $headers = []) Temporarily unavailable
 * @method static other(string $url, array $headers = []) Redirect after put or post (disable re-triggering the request)
 * @method static moved(string $url, array $headers = []) Link moved permanently (indicates reorganization)
 */
final class Redirect extends BaseResponse
{
    /**
     * Redirect to a location
     *
     * Reference: https://developer.mozilla.org/en-US/docs/Web/HTTP/Redirections
     *
     * @param string $response_type
     * @param array $parameters
     * @throws Exception
     */
    public static function __callStatic(string $response_type = 'to', array $parameters = [])
    {
        $available = match (URL::getMethod('converted')) {
            'GET' => [
                // Permanent redirection
                'moved' => 301, // link moved permanently (indicates reorganization)
                // Temporary redirection
                'to' => 302, // temporarily unavailable
            ],
            ['POST', 'PUT'] => [
                // Permanent redirection
                'moved' => 308, // link moved permanently (indicates reorganization but body not changed)
                // Temporary redirection
                'other' => 303, // redirect for disabling submission re-trigger
                'to' => 307, // temporarily unavailable
            ],
            default => [
                'moved' => 308,
                'to' => 307,
            ]
        };

        if (!isset($available[$response_type])) {
            throw new \InvalidArgumentException("Invalid redirect command (found '{$response_type}')!");
        }
        if (empty($parameters[0]) || !filter_var($parameters[0], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL');
        }
        ResponseDepot::setContent(sprintf(
            '<!DOCTYPE html>
                <html>
                    <head>
                        <meta charset="UTF-8" />
                        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />
                        <title>Redirecting to %1$s</title>
                    </head>
                    <body>
                        Redirecting to <a href="%1$s">%1$s</a>.
                    </body>
                </html>',
            htmlspecialchars($parameters[0], ENT_QUOTES)
        ));
        if (!empty($parameters[1])) {
            foreach ($parameters[1] as $name => $value) {
                ResponseDepot::setHeader($name, $value, false);
            }
        }
        ResponseDepot::$code = $available[$response_type];
        ResponseDepot::setHeader('Location', $parameters[0], false);
        (new self)->helloWorld();
    }
}