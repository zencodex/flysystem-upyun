<?php

namespace ZenCodex\Support\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use ZenCodex\Support\Flysystem\Adapter\UpyunAdapter;
use ZenCodex\Support\Flysystem\Plugins\ImagePreviewPlugin;

class UpyunServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('upyun', function ($app, $config) {
            $adapter = new UpyunAdapter((object)$config);
            $filesystem = new Filesystem($adapter, new Config(['disable_asserts' => true]));
            $filesystem->addPlugin(new ImagePreviewPlugin());
            return $filesystem;
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
