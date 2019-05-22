<?php

namespace ZenCodex\Support\Flysystem\Plugin;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Class ImagePreviewPlugin
 * 
 * @package ZenCodex\Support\Flysystem\Plugin
 */
class ImagePreviewPlugin extends AbstractPlugin
{
    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'getUrl';
    }

    public function handle($path = null)
    {
        return $this->filesystem->getAdapter()->getUrl($path);
    }
}