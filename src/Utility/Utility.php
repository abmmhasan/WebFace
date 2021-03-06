<?php


namespace AbmmHasan\WebFace\Utility;


abstract class Utility
{
    /**
     * @param $asset
     * @param $key
     * @return mixed|null
     */
    protected static function getValue($asset, $key)
    {
        if (empty($key)) {
            return $asset;
        }

        return $asset[$key] ?? null;
    }
}