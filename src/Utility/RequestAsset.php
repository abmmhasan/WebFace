<?php


namespace AbmmHasan\WebFace\Utility;

use AbmmHasan\Bucket\Functional\Arrject;

final class RequestAsset extends Utility
{
    private static Arrject $query;
    private static Arrject $post;
    private static Arrject $files;
    private static Arrject $server;
    private static Arrject $cookie;
    private static $body;
    private static $raw;

    /**
     * Get Server Info
     *
     * @param string|null $key
     * @return mixed
     */
    public static function server(string $key = null): mixed
    {
        self::$server ??= new Arrject($_SERVER);

        return self::getValue(self::$server, $key);
    }

    /**
     * Get Cookies
     *
     * @param string|null $key
     * @return mixed
     */
    public static function cookie(string $key = null): mixed
    {
        self::$cookie ??= new Arrject($_COOKIE);

        return self::getValue(self::$cookie, $key);
    }

    /**
     * Get query params
     *
     * @param string|null $key
     * @return mixed
     */
    public static function query(string $key = null): mixed
    {
        self::$query ??= new Arrject($_GET);

        return self::getValue(self::$query, $key);
    }

    /**
     * Get post content
     *
     * @param string|null $key
     * @return mixed
     */
    public static function post(string $key = null): mixed
    {
        self::$post ??= new Arrject($_POST);

        return self::getValue(self::$post, $key);
    }

    /**
     * Get raw input (Not available with enctype="multipart/form-data")
     *
     * @return false|string
     */
    public static function raw(): bool|string
    {
        return self::$raw ??= file_get_contents('php://input');
    }

    /**
     * Get parsed body by Content Type
     *
     * @param string|null $key
     * @return mixed
     */
    public static function parsedBody(string $key = null): mixed
    {
        if (!isset(self::$body) && ($rawBody = self::raw())) {
            self::$body = new Arrject(match (Headers::content('type')) {
                'application/json' => json_decode($rawBody, true),
                'application/xml' => self::getParsedXML($rawBody),
                default => []
            });
        }
        return self::getValue(self::$body, $key);
    }

    /**
     * XML parser
     *
     * @param $input
     * @return mixed
     */
    private static function getParsedXML($input): mixed
    {
        $input = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $input);
        $input = preg_replace('/\s\s+/', " ", $input);
        $input = simplexml_load_string($input);
        return json_decode(json_encode($input), true);
    }

    /**
     * Get file list
     *
     * @param string|null $key
     * @return mixed
     */
    public static function files(string $key = null): mixed
    {
        if (!isset(self::$files)) {
            $files = [];
            if (!empty($input = $_FILES)) {
                self::arrangeFiles($input, $files);
            }
            self::$files = new Arrject($files);
        }
        return self::getValue(self::$files, $key);
    }

    /**
     * @param $src
     * @param $files
     */
    private static function arrangeFiles($src, &$files)
    {
        // an array with these keys is a "target" for us (pre-sorted)
        $tgtKeys = ['error', 'name', 'size', 'tmp_name', 'type'];

        // the keys of the source array (sorted so that comparisons work
        // regardless of original order)
        $srcKeys = array_keys((array)$src);
        sort($srcKeys);

        // is the source array a target?
        if ($srcKeys == $tgtKeys) {
            // get error, name, size, etc
            foreach ($srcKeys as $key) {
                if (is_array($src[$key])) {
                    // multiple file field names for each error, name, size, etc.
                    foreach ((array)$src[$key] as $field => $value) {
                        $files[$field][$key] = $value;
                    }
                } else {
                    // the key itself is error, name, size, etc., and the
                    // target is already the file field name
                    $files[$key] = $src[$key];
                }
            }
        } else {
            // not a target, create sub-elements and init them too
            foreach ($src as $key => $val) {
                $files[$key] = array();
                self::arrangeFiles($val, $files[$key]);
            }
        }
    }
}
