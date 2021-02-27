<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Utility\EndUser;

class FilterIP
{
    protected $allowed = [];
    protected $forbidden = [];

    public function handle()
    {
        $clientIP = EndUser::ip() ?? $_SERVER['REMOTE_ADDR'];
        return !empty($clientIP) && !$this->isForbidden($clientIP) && $this->isAllowed($clientIP);
    }

    private function isForbidden($clientIP)
    {
        if (empty($this->forbidden)) {
            return false;
        }
        return EndUser::checkIP($this->forbidden, $clientIP);
    }

    private function isAllowed($clientIP)
    {
        if (empty($this->allowed)) {
            return true;
        }
        return EndUser::checkIP($this->allowed, $clientIP);
    }

}