<?php

namespace ZenCodex\Support\Flysystem\Adapter;

use Upyun\Upyun;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToRetrieveMetadata;

class UpyunAdapter implements FilesystemAdapter
{
    /**
     * 外部调用传入的配置参数
     */
    private object $config;

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
     * @return Upyun
     */
    public function getClientHandler(array $configMore = []): Upyun
    {
        $config = new \Upyun\Config($this->config->bucket, $this->config->operator, $this->config->password);
        $config->useSsl = $this->config->protocol === 'https';
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
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->getClientHandler()->has($path);
        } catch (\Exception $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    /**
     * @param string          $path
     * @param string|resource $contents
     * @param Config          $config
     *
     * @return void
     */
    public function write(string $path, $contents, Config $config): void
    {
        try {
            $this->getClientHandler()->write($path, $contents);
        } catch (\Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage());
        }
    }

    /**
     * @param string $path
     * @param        $contents
     * @param Config $config
     *
     * @return void
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
        try {
            return file_get_contents($this->getUrl($path));
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage());
        }
    }

    /**
     * @param string $path
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        try {
            return fopen($this->getUrl($path), 'r');
        } catch (\Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage());
        }
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
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage());
        }
    }

    /**
     * @param string $path
     *
     * @return void
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
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->getClientHandler()->createDir($path);
        } catch (\Exception $exception) {
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage());
        }
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

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path);
    }

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @param Config $config
     *
     * @return void
     */
    protected function rename(string $path, string $newpath, Config $config)
    {
        $this->copy($path, $newpath, $config);
        $this->delete($path);
    }

    /**
     * @param string $path
     *
     * @return FileAttributes
     */
    protected function getMetadata(string $path): FileAttributes
    {
        $headers = get_headers($this->getUrl($path), 1);

        return FileAttributes::fromArray([
            StorageAttributes::ATTRIBUTE_PATH          => $path,
            StorageAttributes::ATTRIBUTE_MIME_TYPE     => $headers['Content-Type'],
            StorageAttributes::ATTRIBUTE_FILE_SIZE     => (int)$headers['Content-Length'],
            StorageAttributes::ATTRIBUTE_LAST_MODIFIED => strtotime($headers['Last-Modified']),
        ]);
    }

    /**
     * Normalize the file info.
     *
     * @param array  $stats
     * @param string $directory
     *
     * @return array
     */
    protected function normalizeFileInfo(array $stats, string $directory): array
    {
        $filePath = ltrim($directory . '/' . $stats['name'], '/');

        return [
            'type'      => $stats['type'] === 'N' ? 'file' : 'folder',
            'path'      => $filePath,
            'timestamp' => $stats['time'],
            'size'      => $stats['size'],
        ];
    }

    /**
     * @param $domain
     *
     * @return string
     */
    protected function normalizeHost($domain): string
    {
        if (0 !== stripos($domain, 'https://') && 0 !== stripos($domain, 'http://')) {
            $domain = $this->config->protocol . "://{$domain}";
        }

        return rtrim($domain, '/') . '/';
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

        $results = $this->getClientHandler()->read($path, null, ['X-List-Limit' => 100, 'X-List-Iter' => null]);
        while (1) {
            foreach ($results['files'] as $files) {
                $list[] = $this->normalizeFileInfo($files, $path);
            }

            if ($deep === false || $results['is_end']) {
                break;
            }

            $results = $this->getClientHandler()->read($path, null, ['X-List-Limit' => 100, 'X-List-Iter' => $results['iter']]);
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
        try {
            $this->rename($source, $destination, $config);
        } catch (\Exception $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * @param string $source
     * @param string $destination
     * @param Config $config
     *
     * @return void
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->writeStream($destination, fopen($this->getUrl($source), 'r'), $config);
        } catch (\Exception $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    /**
     * @param $path
     *
     * @return string
     */
    public function getUrl($path): string
    {
        return (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) ?
            $path : $this->normalizeHost($this->config->domain) . ltrim($path, '/');
    }
}
