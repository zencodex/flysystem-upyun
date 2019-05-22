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

$bucket = 'your-bucket-name';
$operator = 'oparator-name';
$password = 'operator-password';
$domain = 'xxxxx.b0.upaiyun.com'; // 或者 https://xxxx.b0.upaiyun.com

$adapter = new UpyunAdapter($bucket, $operator, $password, $domain);
$flysystem = new Filesystem($adapter);

```

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
