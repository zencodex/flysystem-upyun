<?php

namespace ZenCodex\Support\Flysystem;

use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use ZenCodex\Support\Flysystem\Adapter\UpyunAdapter;

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
            return new FilesystemAdapter(new Filesystem($adapter), $adapter, ['disable_asserts' => true]);
        });
    }
}
