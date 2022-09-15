<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Request\Asset\CommonAsset;
use AbmmHasan\WebFace\Request\Asset\EndUser;

class FilterIP
{
    protected array $allowed = [];
    protected array $forbidden = [];

    /**
     * Checks if Client IP is eligible or not!
     *
     * @return bool
     */
    public function handle(): bool
    {
        $clientIP = EndUser::instance()->ip() ?? CommonAsset::instance()->server('REMOTE_ADDR');
        return !empty($clientIP) && !$this->isForbidden($clientIP) && $this->isAllowed($clientIP);
    }

    /**
     * @param $clientIP
     * @return bool
     */
    private function isForbidden($clientIP): bool
    {
        if (empty($this->forbidden)) {
            return false;
        }
        return EndUser::instance()->checkIP($this->forbidden, $clientIP);
    }

    /**
     * @param $clientIP
     * @return bool
     */
    private function isAllowed($clientIP): bool
    {
        if (empty($this->allowed)) {
            return true;
        }
        return EndUser::instance()->checkIP($this->allowed, $clientIP);
    }

}
