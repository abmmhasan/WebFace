<?php

if (!function_exists('responseFlush')) {
    /**
     * Send response
     */
    function responseFlush()
    {
        AbmmHasan\WebFace\Response::instance()->send();
    }
}

if (!function_exists('httpDate')) {
    /**
     * Converts any recognizable date format to an HTTP date.
     *
     * @param mixed $date The incoming date value.
     * @return string A formatted date.
     * @throws Exception
     */
    function httpDate($date = null)
    {
        if ($date instanceof \DateTime) {
            $date = \DateTimeImmutable::createFromMutable($date);
        } else {
            $date = new \DateTime($date);
        }

        try {
            $date->setTimeZone(new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            $date = new \DateTime('0001-01-01', new \DateTimeZone('UTC'));
        } finally {
            return $date->format('D, d M Y H:i:s') . ' GMT';
        }
    }
}