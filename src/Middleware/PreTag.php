<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Request\Asset\Headers;
use AbmmHasan\WebFace\Request\Asset\URL;
use AbmmHasan\WebFace\Router\Asset\Depository;
use AbmmHasan\WebFace\Router\Asset\Settings;
use Exception;

class PreTag
{
    private array $asset;
    protected Depository $depot;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->depot = Depository::instance();
        $this->loadAsset();
    }

    /**
     * @return array|bool
     * @throws Exception
     */
    public function handle(): array|bool
    {
        return $this->compareDependency();
    }

    /**
     * Set pre-tag assets
     *
     * @param $path
     * @param $tag
     * @return bool|int
     * @throws Exception
     */
    public function set($path, $tag): bool|int
    {
        if (empty(Settings::$preTagFileLocation)) {
            throw new Exception("PreTag file location not defined!");
        }
        if (!file_exists(Settings::$preTagFileLocation)) {
            throw new Exception("Unable to locate PreTag file!");
        }
        $this->setByPath($path, $tag);
        if (!empty($this->asset)) {
            return file_put_contents(Settings::$preTagFileLocation, json_encode($this->asset), LOCK_EX);
        }
        return false;
    }

    /**
     * Populate asset from file
     */
    private function loadAsset(): void
    {
        if (file_exists(Settings::$preTagFileLocation)) {
            $this->asset = json_decode(file_get_contents(Settings::$preTagFileLocation), true);
        }
    }

    /**
     * Set tag by route path
     *
     * @param $path
     * @param $tag
     * @throws Exception
     */
    private function setByPath($path, $tag): void
    {
        $list = Depository::instance()
            ->getResource('list');
        if (empty($list) || !in_array($path, $list)) {
            throw new Exception("Route path '{$path}' invalid!");
        }
        $this->asset[$path] = $tag;
    }

    /**
     * Compare dependency
     *
     * @return bool|int[]
     * @throws Exception
     */
    private function compareDependency(): array|bool
    {
        $signature = $this->depot->getSignature('uri');
        if (!isset($this->asset[$signature])) {
            return true;
        }
        $dependencies = Headers::instance()->responseDependency();
        if (URL::instance()->getMethod('converted') === 'GET' &&
            !empty($dependencies['if_none_match']) &&
            in_array($this->asset[$signature], $dependencies['if_none_match'])) {
            return [
                'code' => 304
            ];
        }
        return true;
    }

}
