<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Utility\ClientIP;

class FilterIP
{
    protected $allowed = [];
    protected $forbidden = [];

    public function handle()
    {
        $clientIP = ClientIP::get() ?? $_SERVER['REMOTE_ADDR'];
        return !empty($clientIP) && !$this->isForbidden($clientIP) && $this->isAllowed($clientIP);
    }

    private function isForbidden($clientIP)
    {
        if (empty($this->forbidden)) {
            return false;
        }
        return ClientIP::check($this->forbidden, $clientIP);
    }

    private function isAllowed($clientIP)
    {
        if (empty($this->allowed)) {
            return true;
        }
        return ClientIP::check($this->allowed, $clientIP);
    }

}