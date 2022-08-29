<?php


namespace AbmmHasan\WebFace\Common;


trait Value
{
    /**
     * @param $asset
     * @param $key
     * @return mixed
     */
    protected function find($asset, $key): mixed
    {
        if ($key === null) {
            return $asset;
        }

        return $asset[$key] ?? null;
    }
}