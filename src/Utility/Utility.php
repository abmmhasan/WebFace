<?php


namespace AbmmHasan\WebFace\Utility;


class Utility
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