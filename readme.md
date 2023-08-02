[![Latest Stable Version](https://poser.pugx.org/zencodex/flysystem-upyun/v/stable)](https://packagist.org/packages/zencodex/flysystem-upyun)
[![Total Downloads](https://poser.pugx.org/zencodex/flysystem-upyun/downloads)](https://packagist.org/packages/zencodex/flysystem-upyun)
[![Latest Unstable Version](https://poser.pugx.org/zencodex/flysystem-upyun/v/unstable)](https://packagist.org/packages/zencodex/flysystem-upyun)
[![composer.lock](https://poser.pugx.org/zencodex/flysystem-upyun/composerlock)](https://packagist.org/packages/zencodex/flysystem-upyun)
[![License](https://poser.pugx.org/zencodex/flysystem-upyun/license)](https://packagist.org/packages/zencodex/flysystem-upyun)

> 已支持到 php >= 8.2, laravel 10.x

# Requirement

- PHP >= 5.5.9

# 安装

直接可以通过 composer 来安装:
```sh
$ composer require "zencodex/flysystem-upyun"
```

# 使用

## 1. 直接使用

```php

use League\Flysystem\Filesystem;
use ZenCodex\Support\Flysystem\Adapter\UpyunAdapter

$config => [
    'driver'        => 'upyun',
    'bucket'        => '', // 服务名字
    'operator'      => '', // 操作员的名字
    'password'      => '', // 操作员的密码
    'domain'        => '', // 服务分配的域名
    'protocol'      => 'https', // 服务使用的协议，如需使用 http，在此配置 http
];

$adapter = new UpyunAdapter((object)$config);

// 或在 Laravel 中获取 $adapter
$adapter = Storage::disk('upyun')->getAdapter();

$adapter->write('file.md', 'contents');
$adapter->writeStream('file.md', fopen('path/to/your/local/file.jpg', 'r'));

$adapter->rename('foo.md', 'bar.md');
$adapter->copy('foo.md', 'foo2.md');
$adapter->delete('file.md');
$adapter->getUrl('/path/foo/bar/file.md');

$adapter->fileExists('file.md');
$adapter->directoryExists('path/to/dir');
$adapter->read('file.md');

// ...
// $adapter 详细调用方法可参考: src/Adapter/UpyunAdapter.php

// $clientHandler 为 Upyun::class, 直接调用 Upyun 内的方法
$clientHandler = $adapter->getClientHandler();
$clientHandler->purge($remoteUrl);
$clientHandler->usage();
```

> 

## 2. 在 Laravel 中使用

1. `config/app.php` 中添加 `UpyunServiceProvider`:

```php

'providers' => [
    // ...
    ZenCodex\Support\Flysystem\UpyunServiceProvider::class,
],
```

2. `config/filesystems.php` 的 `disks` 中添加下面的配置：

```php
'disks' => [
    // ...

    'upyun' => [
        'driver'        => 'upyun',
        'bucket'        => env('UPYUN_BUCKET', ''),// 服务名字
        'operator'      => env('UPYUN_OPERATOR_NAME', ''), // 操作员的名字
        'password'      => env('UPYUN_OPERATOR_PASSWORD', ''), // 操作员的密码
        'domain'        => env('UPYUN_DOMAIN', ''), // 服务分配的域名
        'protocol'     => 'https', // 服务使用的协议，如需使用 http，在此配置 http
    ]
]
```

3. Laravel Storage 标准 API 调用

```php
$disk = Storage::disk('upyun');

$disk->write('file.md', 'contents');
$disk->writeStream('file.md', fopen('path/to/your/local/file.jpg', 'r'));

$disk->rename('foo.md', 'bar.md');
$disk->copy('foo.md', 'foo2.md');
$disk->delete('file.md');

$disk->fileExists('file.md');
$disk->directoryExists('path/to/dir');
$disk->read('file.md');

$disk->listContents();
$disk->fileSize('file.md');
$disk->mimeType('file.md');
$disk->url('/path/foo/bar/file.md');
```
