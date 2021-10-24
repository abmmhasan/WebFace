<?php


namespace AbmmHasan\WebFace\Utility;


abstract class Utility
{
    /**
     * @param $asset
     * @param $key
     * @return mixed
     */
    protected static function getValue($asset, $key): mixed
    {
        if (empty($key)) {
            return $asset;
        }

        return $asset[$key] ?? null;
    }
}