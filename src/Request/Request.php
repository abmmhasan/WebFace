<?php

namespace AbmmHasan\WebFace\Request;

use AbmmHasan\Bucket\Functional\Arrject;
use AbmmHasan\WebFace\Request\Asset\BaseRequest;
use Exception;

/**
 * Request
 *
 * @method server(string $key = null) Get Server Info
 * @method cookie(string $key = null) Get Cookies
 * @method headers(string $key = null) Get HTTP headers
 * @method method() Get request method (converted)
 * @method originalMethod() Get request method (original)
 * @method contentHeader(string $key = null) Get parsed content headers
 * @method accept(string $key = null) Get parsed Accept headers
 * @method url(string $key = null) Get current URL (parsed)
 * @method dependencyHeader(string $key = null) Get response dependency
 * @method client(string $key = null) Get user info
 * @method xhr() Is the request originated as XMLHttpRequest?
 * @method post(string $key = null) Get post content
 * @method query(string $key = null) Get query params
 * @method files(string $key = null) Get uploaded file(s)
 * @method rawBody() Get raw input (Not available with enctype = "multipart/form-data")
 * @method parsedBody(string $key = null) Get parsed body by Content Type
 */
class Request extends BaseRequest
{
    /**
     *
     * Read-only access to property objects.
     *
     * @param string $key The name of the property object to read.
     * @return mixed The property object.
     */
    public function __get(string $key)
    {
        return $this->request->$key;
    }

    /**
     * Get all the request element
     *
     * @return Arrject all the request element
     */
    public function all(): Arrject
    {
        return $this->request;
    }

    public function __toString()
    {
        return $this->request->toJson();
    }

    /**
     * Get additional request assets
     *
     * @param $name
     * @param $arg
     * @return mixed|null
     * @throws Exception
     */
    public function __call($name, $arg)
    {
        $asset = $this->getAsset($name);
        if ($arg) {
            return $asset->$arg ?? null;
        }
        return $asset;
    }
}
