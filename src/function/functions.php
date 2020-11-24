<?php

use AbmmHasan\WebFace\Response;

if (!function_exists('responseFlush')) {
    /**
     * Send response
     *
     * @param $classOrClosure
     * @param mixed ...$parameters
     * @return Container
     */
    function responseFlush()
    {
        die('hi');
        Response::instance()->send();
    }
}