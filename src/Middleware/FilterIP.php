<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Request\Asset\CommonAsset;
use AbmmHasan\WebFace\Request\Asset\EndUser;

class FilterIP
{
    protected array $allowed = [];
    protected array $forbidden = [];
    private string $clientIP;

    public function __construct()
    {
        $this->clientIP = EndUser::instance()->ip() ?? CommonAsset::instance()->server('REMOTE_ADDR');
    }

    /**
     * Checks if Client IP is eligible or not!
     *
     * @return bool
     */
    public function handle(): bool
    {

        return !empty($this->clientIP) && !$this->isForbidden() && $this->isAllowed();
    }

    /**
     * @return bool
     */
    private function isForbidden(): bool
    {
        if (empty($this->forbidden)) {
            return false;
        }
        return EndUser::instance()->checkIP($this->forbidden, $this->clientIP);
    }

    /**
     * @return bool
     */
    private function isAllowed(): bool
    {
        if (empty($this->allowed)) {
            return true;
        }
        return EndUser::instance()->checkIP($this->allowed, $this->clientIP);
    }

}
