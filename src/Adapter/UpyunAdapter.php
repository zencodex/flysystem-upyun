<?php

namespace ZenCodex\Support\Flysystem\Adapter;

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
 * ZComposer 镜像已经开源，如果有兴趣可以访问 <https://github.com/zencodex/composer-mirror>
 */

use Upyun\Upyun;
use League\Flysystem\Config;
use League\Flysystem\Adapter\AbstractAdapter;

class UpyunAdapter extends AbstractAdapter
{
    /**
     * 外部调用传入的配置参数
     */
    private $config;

    /**
     * UpyunAdapter constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = (object)$config;

        if (!isset($this->config->domain)) {
            $this->config->domain = '';
        }

        if (!isset($this->config->protocol)) {
            $this->config->protocol = 'https';
        }

        if (!isset($this->config->timeout)) {
            $this->config->timeout = 600;
        }

        if (!isset($this->config->uploadType)) {
            $this->config->uploadType = 'AUTO';
        }

        if (!isset($this->config->sizeBoundary)) {
            $this->config->sizeBoundary = 121457280;
        }
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     */
    public function write($path, $contents, Config $config)
    {
        try {
            $this->getClientHandler()->write($path, $contents);
            return $path;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     */
    public function writeStream($path, $resource, Config $config)
    {
        try {
            $this->getClientHandler()->write($path, $resource);
            return $path;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * @param string $path
     * @param string $newpath
     */
    public function rename($path, $newpath)
    {
        $this->copy($path,$newpath);
        return $this->delete($path);
    }

    /**
     * @param string $path
     * @param string $newpath
     */
    public function copy($path, $newpath)
    {
        $this->writeStream($newpath, fopen($this->getUrl($path), 'r'), new Config());
        return true;
    }

    /**
     * @param string $path
     */
    public function delete($path)
    {
        return $this->getClientHandler()->delete($path);
    }

    /**
     * @param string $dirname
     */
    public function deleteDir($dirname)
    {
        return $this->getClientHandler()->deleteDir($dirname);
    }

    /**
     * @param string $dirname
     * @param Config $config
     */
    public function createDir($dirname, Config $config)
    {
        return $this->getClientHandler()->createDir($dirname);
    }

    /**
     * @param string $path
     * @param string $visibility
     */
    public function setVisibility($path, $visibility)
    {
        return true;
    }

    /**
     * @param string $path
     */
    public function has($path)
    {
        return $this->getClientHandler()->has($path);
    }

    /**
     * @param string $path
     */
    public function read($path)
    {
        $contents = file_get_contents($this->getUrl($path));
        return compact('contents', 'path');
    }

    /**
     * @param string $path
     */
    public function readStream($path)
    {
        $stream = fopen($this->getUrl($path), 'r');
        return compact('stream', 'path');
    }

    /**
     * @param string $directory
     * @param bool $recursive
     */
    public function listContents($directory = '', $recursive = false)
    {
        $list = [];

        $result = $this->getClientHandler()->read($directory, null, [ 'X-List-Limit' => 100, 'X-List-Iter' => null]);

        foreach ($result['files'] as $files) {
            $list[] = $this->normalizeFileInfo($files, $directory);
        }

        return $list;
    }

    /**
     * @param string $path
     */
    public function getMetadata($path)
    {
        return $this->getClientHandler()->info($path);
    }

    /**
     * @param string $path
     */
    public function getType($path)
    {
        $response = $this->getMetadata($path);

        return ['type' => $response['x-upyun-file-type']];
    }

    /**
     * @param string $path
     */
    public function getSize($path)
    {
        $response = $this->getMetadata($path);

        return ['size' => $response['x-upyun-file-size']];
    }

    /**
     * @param string $path
     */
    public function getMimetype($path)
    {
        $headers = get_headers($this->getUrl($path), 1);
        $mimetype = $headers['Content-Type'];
        return compact('mimetype');
    }

    /**
     * @param string $path
     */
    public function getTimestamp($path)
    {
        $response = $this->getMetadata($path);

        return ['timestamp' => $response['x-upyun-file-date']];
    }

    /**
     * @param string $path
     */
    public function getVisibility($path)
    {
        return true;
    }

    /**
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {

        return (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) ? $path : $this->normalizeHost
            ($this->config->domain).$path;
    }

    /**
     * 方便暴露一些没有封装的方法调用
     * @param array $configMore
     * @return void
     */
    public function getClientHandler($configMore = [])
    {
        $config = new \Upyun\Config($this->config->bucket, $this->config->operator, $this->config->password);
        $config->useSsl = $this->config->protocol === 'https' ? true : false;
        $config->timeout = $this->config->timeout;
        $config->sizeBoundary = $this->config->sizeBoundary;
        $config->uploadType = $this->config->uploadType;

        foreach ($configMore as $key => $value) {
            $config->$key = $value;
        }
        return new Upyun($config);
    }

    /**
     * Normalize the file info.
     * 
     * @param array $stats
     * @param string $directory
     * 
     * @return array
     */
    protected function normalizeFileInfo(array $stats, string $directory)
    {
        $filePath = ltrim($directory . '/' . $stats['name'], '/');

        return [
            'type' => $this->getType($filePath)['type'],
            'path' => $filePath,
            'timestamp' => $stats['time'],
            'size' => $stats['size'],
        ];
    }

    /**
     * @param $domain
     * @return string
     */
    protected function normalizeHost($domain)
    {
        if (0 !== stripos($domain, 'https://') && 0 !== stripos($domain, 'http://')) {
            $domain = $this->config->protocol."://{$domain}";
        }

        return rtrim($domain, '/').'/';
    }
}


