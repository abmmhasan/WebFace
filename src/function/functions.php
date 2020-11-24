<?php

use AbmmHasan\WebFace\Response;

if (!function_exists('responseFlush')) {
    /**
     * Send response
     */
    function responseFlush()
    {
        Response::instance()->send();
    }
}