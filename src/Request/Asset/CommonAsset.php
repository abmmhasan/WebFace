<?php


namespace AbmmHasan\WebFace\Request\Asset;

use AbmmHasan\Bucket\Functional\Arrject;
use AbmmHasan\OOF\Fence\Single;
use AbmmHasan\WebFace\Response\Asset\HTTPResource;
use AbmmHasan\WebFace\Response\Asset\ResponseDepot;
use Exception;

final class CommonAsset
{
    private Arrject $query;
    private Arrject $post;
    private Arrject $files;
    private Arrject $server;
    private Arrject $cookie;
    private Arrject $body;
    private string $raw;

    use Value, Single;

    /**
     * Get Server Info
     *
     * @param string|null $key
     * @return mixed
     */
    public function server(string $key = null): mixed
    {
        $this->server ??= new Arrject($_SERVER);

        return $this->find($this->server, $key);
    }

    /**
     * Get Cookies
     *
     * @param string|null $key
     * @return mixed
     */
    public function cookie(string $key = null): mixed
    {
        $this->cookie ??= new Arrject($_COOKIE);

        return $this->find($this->cookie, $key);
    }

    /**
     * Get query params
     *
     * @param string|null $key
     * @return mixed
     */
    public function query(string $key = null): mixed
    {
        $this->query ??= new Arrject($_GET);

        return $this->find($this->query, $key);
    }

    /**
     * Get post content
     *
     * @param string|null $key
     * @return mixed
     */
    public function post(string $key = null): mixed
    {
        $this->post ??= new Arrject($_POST);

        return $this->find($this->post, $key);
    }

    /**
     * Get raw input (Not available with enctype="multipart/form-data")
     *
     * @return string|bool
     * @throws Exception
     */
    public function raw(): string|bool
    {
        return $this->raw ??= (Headers::instance()->content('type') === 'multipart/form-data'
            ? false
            : file_get_contents('php://input'));
    }

    /**
     * Get parsed body by Content Type
     *
     * @param string|null $key
     * @return mixed
     * @throws Exception
     */
    public function parsedBody(string $key = null): mixed
    {
        if (!isset($this->body) && ($rawBody = $this->raw()) !== false) {
            $type = Headers::instance()->content('type');
            $body = [];
            if ($type === 'application/json' ||
                preg_match_all('/^application\/(.+\+)?json$/', $type ?? '') === 1) {
                $body = json_decode($rawBody, true);
            }
            if ($body === null) {
                ResponseDepot::setStatus(415);
                ResponseDepot::setContent(HTTPResource::$statusList[]);
                responseFlush();
                return null;
            }
            $this->body = new Arrject($body);
        }
        return $this->find($this->body, $key);
    }

    /**
     * Get file list
     *
     * @param string|null $key
     * @return mixed
     */
    public function files(string $key = null): mixed
    {
        if (!isset($this->files)) {
            $files = [];
            if (!empty($input = $_FILES)) {
                $this->arrangeFiles($input, $files);
            }
            $this->files = new Arrject($files);
        }
        return $this->find($this->files, $key);
    }

    /**
     * Arrange files & required properties in order
     *
     * @param $src
     * @param $files
     */
    private function arrangeFiles($src, &$files): void
    {
        // an array with these keys is a "target" for us (pre-sorted)
        $tgtKeys = ['error', 'name', 'size', 'tmp_name', 'type'];

        // the keys of the source array (sorted so that comparisons work
        // regardless of original order)
        $srcKeys = array_keys((array)$src);
        sort($srcKeys);

        // is the source array a target?
        if ($srcKeys === $tgtKeys) {
            // get error, name, size, etc
            foreach ($srcKeys as $key) {
                if (is_array($src[$key])) {
                    // multiple file field names for each error, name, size, etc.
                    foreach ($src[$key] as $field => $value) {
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
                $this->arrangeFiles($val, $files[$key]);
            }
        }
    }
}
