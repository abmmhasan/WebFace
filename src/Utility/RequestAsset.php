<?php


namespace AbmmHasan\WebFace\Utility;


final class RequestAsset extends Utility
{
    private static Arrject $query;
    private static Arrject $post;
    private static Arrject $files;
    private static Arrject $server;
    private static Arrject $cookie;

    public static function server($key = null)
    {
        self::$server ??= new Arrject($_SERVER);

        return self::getValue(self::$server, $key);
    }

    public static function cookie($key = null)
    {
        self::$cookie ??= new Arrject($_COOKIE);

        return self::getValue(self::$cookie, $key);
    }

    public static function query($key = null)
    {
        self::$query ??= new Arrject($_GET);

        return self::getValue(self::$query, $key);
    }

    public static function post($key = null)
    {
        self::$post ??= new Arrject($_POST);

        return self::getValue(self::$post, $key);
    }

    public static function files($key = null)
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

    private static function arrangeFiles($src, &$tgt)
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
                        $tgt[$field][$key] = $value;
                    }
                } else {
                    // the key itself is error, name, size, etc., and the
                    // target is already the file field name
                    $tgt[$key] = $src[$key];
                }
            }
        } else {
            // not a target, create sub-elements and init them too
            foreach ($src as $key => $val) {
                $tgt[$key] = array();
                self::arrangeFiles($val, $tgt[$key]);
            }
        }
    }
}
