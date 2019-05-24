```php
/**
 * Adapter 实现 参考「jellybool/flysystem-upyun」
 * 
 * 为啥要重造这个轮子：
 * 
 * 1. 这个包封装的主要目的是给 ZComposer 镜像远程存储使用，如果又拍云接口规则变动，利于快速修复
 * 2. ZComposer 镜像每天在又拍云里维护和同步上百万个文件，对接口调用的要求更为苛刻
 *    如最近刚修复的因upyun/php-sdk并行上传，触发又拍云同名文件上传间隔检测问题，解决方法强制 $config->uploadType = 'BLOCK'
 * 3. ZComposer 需要更多的自定义配置，所以灵活性做了重构，见 $this->getClientHandler
 * 
 * ZComposer 镜像已经开源，如果有兴趣可以访问 [https://github.com/zencodex/composer-mirror](https://github.com/zencodex/composer-mirror)
 */
```

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

$adapter = new UpyunAdapter($config);
// 针对云盘操作，建议添加 disable_asserts
// 意思是不对远程文件是否已经存在做判断，强制覆盖。否则会增加一次API调用
$flysystem = new Filesystem($adapter, new Config([ 'disable_asserts' => true]));

// 添加插件 ClientHandlerPlugin 
// $filesystem->addPlugin(new ClientHandlerPlugin());
// $client = $filesystem->getClientHandler(); 
// $client->purge('http://packagist.laravel-china.org/packages.json');

```

`$client = $filesystem->getClientHandler() 等同于 $client = new Upyun($serviceConfig) `，更多参考  https://github.com/upyun/php-sdk

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

# API 和方法调用

```php
$flysystem->read('file.md');
$flysystem->copy('foo.md', 'foo2.md');
$flysystem->delete('file.md');

$flysystem->has('file.md');
$flysystem->listContents();
$flysystem->rename('foo.md', 'bar.md');

$flysystem->write('file.md', 'contents');
$flysystem->writeStream('file.md', fopen('path/to/your/local/file.jpg', 'r'));

$flysystem->update('file.md', 'new contents');
$flysystem->updateStram('file.md', fopen('path/to/your/local/file.jpg', 'r'));

$flysystem->getSize('file.md');
$flysystem->getMimetype('file.md');
$flysystem->getTimestamp('file.md');
$flysystem->getMetadata('file.md');
$flysystem->getUrl('file.md');
```
