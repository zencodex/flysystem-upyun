<?php

namespace ZenCodex\Support\Flysystem\Adapter;

use Upyun\Upyun;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\FilesystemAdapter;

class UpyunAdapter implements FilesystemAdapter
{
    /**
     * 外部调用传入的配置参数
     */
    private $config;

    /**
     * UpyunAdapter constructor.
     *
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
     * 方便暴露一些没有封装的方法调用
     *
     * @param array $configMore
     *
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
     * @param string $path
     *
     * @return bool
     * @throws \Exception
     */
    public function fileExists(string $path): bool
    {
        return $this->getClientHandler()->has($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    /**
     * @param string $path
     * @param string|resource $contents
     * @param Config $config
     *
     * @return void
     * @throws \Exception
     */
    public function write(string $path, $contents, Config $config): void
    {
        $this->getClientHandler()->write($path, $contents);
    }

    /**
     * @param string $path
     * @param        $contents
     * @param Config $config
     *
     * @return void
     * @throws \League\Flysystem\FilesystemException
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function read(string $path): string
    {
        $contents = file_get_contents($this->getUrl($path));
        return compact('contents', 'path');
    }

    /**
     * @param string $path
     *
     * @return array|resource
     */
    public function readStream(string $path)
    {
        $stream = fopen($this->getUrl($path), 'r');
        return compact('stream', 'path');
    }

    /**
     * @param string $path
     *
     * @return void
     */
    public function delete(string $path): void
    {
        try {
            $this->getClientHandler()->delete($path);
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path);
        }
    }

    /**
     * @param string $path
     *
     * @return void
     * @throws \League\Flysystem\FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        $this->delete($path);
    }

    /**
     * @param string $path
     * @param Config $config
     *
     * @return void
     * @throws \Exception
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->getClientHandler()->createDir($dirname);
    }

    /**
     * @param string $path
     * @param string $visibility
     *
     * @return void
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path);
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        $headers = get_headers($this->getUrl($path), 1);
        $mimetype = $headers['Content-Type'];
        return compact('mimetype');
    }

    public function lastModified(string $path): FileAttributes
    {
        $response = $this->getMetadata($path);
        return $response['x-upyun-file-date'];
    }

    /**
     * @param string $path
     * @param string $newpath
     */
    protected function rename($path, $newpath)
    {
        $this->copy($path, $newpath);
        return $this->delete($path);
    }

    /**
     * @param string $path
     */
    protected function getMetadata($path)
    {
        return $this->getClientHandler()->info($path);
    }

    /**
     * @param string $path
     */
    protected function getType($path)
    {
        $response = $this->getMetadata($path);

        return ['type' => $response['x-upyun-file-type']];
    }

    /**
     * Normalize the file info.
     *
     * @param array  $stats
     * @param string $directory
     *
     * @return array
     */
    protected function normalizeFileInfo(array $stats, string $directory)
    {
        $filePath = ltrim($directory . '/' . $stats['name'], '/');

        return [
            'type'      => $this->getType($filePath)['type'],
            'path'      => $filePath,
            'timestamp' => $stats['time'],
            'size'      => $stats['size'],
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

        return rtrim($domain, '/') . '/';
    }

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        $response = $this->getMetadata($path);

        return ['size' => $response['x-upyun-file-size']];
    }

    /**
     * @param string $path
     * @param bool   $deep
     *
     * @return iterable
     * @throws \Exception
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $list = [];

        $result = $this->getClientHandler()->read($path, null, ['X-List-Limit' => 100, 'X-List-Iter' => null]);

        foreach ($result['files'] as $files) {
            $list[] = $this->normalizeFileInfo($files, $path);
        }

        return $list;
    }

    /**
     * @param string $source
     * @param string $destination
     * @param Config $config
     *
     * @return void
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->rename($source, $destination);
    }

    /**
     * @param string $source
     * @param string $destination
     * @param Config $config
     *
     * @return void
     * @throws \League\Flysystem\FilesystemException
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->writeStream($destination, fopen($this->getUrl($source), 'r'), $config);
    }

    /**
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        return (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) ?
            $path : $this->normalizeHost($this->config->domain) . ltrim($path, '/');
    }
}
