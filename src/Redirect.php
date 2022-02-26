<?php


namespace AbmmHasan\WebFace;


use AbmmHasan\WebFace\Base\BaseResponse;

final class Redirect extends BaseResponse
{
    /**
     * Redirect to a location
     *
     * @param string $url
     * @param $status
     * @param array $headers
     */
    public static function __callStatic(string $response_type = 'to', $parameters = [])
    {
        $url = array_shift($parameters);
        $headers = $parameters;
        $available = [
            'created' => 201,
            'movedPermanently' => 301,
            'to' => 302,
            'found' => 302,
            'afterPost' => 303,
            'seeOther' => 303,
            'temporaryRedirect' => 307,
            'permanentRedirect' => 308,
        ];

        if (!(in_array($response_type, array_keys($available)) || in_array($response_type, $available))) {
            throw new \InvalidArgumentException("The HTTP status code is not a redirect (found '{$response_type}').");
        }
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        $instance = new self(sprintf(
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
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
        ),
            $available[$response_type] ?? $response_type,
            $headers);
        $instance->setHeader('Location', $url, false);
        $instance->helloWorld();
    }
}