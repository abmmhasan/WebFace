<?php

namespace AbmmHasan\WebFace;

use AbmmHasan\WebFace\Base\BaseRequest;
use BadMethodCallException;

final class Request extends BaseRequest
{
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
                return $this->$name->$arg ?? null;
            }
            return $this->$name;
        }
        throw new BadMethodCallException("Unknown function $name!");
    }
}
