<?php

namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Base\BaseRequest;
use AbmmHasan\WebFace\Utility\Arrject;
use BadMethodCallException;

final class Request extends BaseRequest
{
    private $request;
    private $files;

    private $allowed = [
        'base',
        'server',
        'client',
        'contents',
        'cookies',
        'files',
        'headers',
        'data',
        'query',
        'method',
        'url',
        'xhr',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->files = new Arrject(self::getFiles($_FILES));
        $this->request = new Arrject(self::getRequest());
    }

    /**
     *
     * Read-only access to property objects.
     *
     * @param string $key The name of the property object to read.
     *
     * @return mixed The property object.
     */
    public function __get(string $key)
    {
        return $this->request->$key;
    }

    public function all()
    {
        return $this->request;
    }

    public function __toString()
    {
        return $this->request->toJson();
    }

    public function __call($name, $arg)
    {
        if (in_array($name, $this->allowed)) {
            if ($arg) {
                return $this->$name->$arg;
            }
            return $this->$name;
        }
        throw new BadMethodCallException("Unknown function $name!");
    }

    private function getFiles(array $input = [])
    {
        $file = [];
        if (!empty($input)) {
            $this->arrangeFiles($input, $file);
        }
        return $file;
    }

    private function getRequest()
    {
        $data = $this->post->toArray() + $this->files->toArray() + $this->query->toArray();
        if ($input = file_get_contents('php://input')) {
            switch ($this->contentHeader->type) {
                case 'application/json':
                    $data += json_decode($input, true);
                    break;
                case 'application/xml':
                    $input = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $input);
                    $input = preg_replace('/\s\s+/', " ", $input);
                    $input = simplexml_load_string($input);
                    $data += json_decode(json_encode($input), true);
                    break;
                default:
                    break;
            }
        }
        return $data;
    }

    private function arrangeFiles($src, &$tgt)
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
                $this->arrangeFiles($val, $tgt[$key]);
            }
        }
    }
}
