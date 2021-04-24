<?php


namespace AbmmHasan\WebFace\Middleware;


use AbmmHasan\WebFace\Support\Settings;
use AbmmHasan\WebFace\Support\Storage;
use AbmmHasan\WebFace\Utility\Headers;
use AbmmHasan\WebFace\Utility\URL;

class PreTag
{
    private $asset = null;

    public function handle()
    {
        $this->loadAsset();
        return self::compareDependency();
    }

    public function set($path, $tag)
    {
        if (empty(Settings::$pre_tag_file_location)) {
            throw new \Exception("PreTag file location not defined!");
        }
        $this->loadAsset();
        $this->setByPath($path, $tag);
        if (!empty($this->asset)) {
            return file_put_contents(
                projectPath() . Settings::$pre_tag_file_location,
                json_encode($this->asset), LOCK_EX
            );
        }
        return false;
    }

    private function loadAsset()
    {
        if (!empty(Settings::$pre_tag_file_location) && file_exists($path = projectPath() . Settings::$pre_tag_file_location)) {
            $this->asset = json_decode(file_get_contents($path), true);
        }
    }

    private function setByPath($path, $tag)
    {
        $list = Storage::getRouteResource('list');
        if (empty($list) || !in_array($path, $list)) {
            throw new \Exception("Route path '{$path}' invalid!");
        }

        $this->asset[$path] = $tag;
    }

    private function compareDependency()
    {
        $route = explode(" ", Storage::getCurrentRoute(), 2);
        if (is_null($this->asset) || !isset($this->asset[$route[1]])) {
            return true;
        }
        $dependencies = Headers::responseDependency();
        $requestMethod = URL::getMethod('converted');
        if (!empty($dependencies['if_none_match']) &&
            in_array($requestMethod, ['GET', 'HEAD']) &&
            in_array($this->asset[$route[1]], $dependencies['if_none_match'])) {
            return [
                'code' => 304
            ];
        }
        return true;
    }

}
