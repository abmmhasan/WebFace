<?php

namespace AbmmHasan\WebFace\Common;

trait StaticSingleInstance
{
    protected static $instance;

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}