<?php

declare(strict_types=1);

namespace Swift\Storage\Provider;

use Swift\Config\SwiftConfig;
use Swift\Model\ObjectVersion;
use Swift\Model\StorageBucket;
use Swift\Model\StorageObject;
use Swift\Storage\StorageProvider;

final class LocalProvider implements StorageProvider
{
    public function __construct(private readonly SwiftConfig $config)
    {
        if (!is_dir($this->config->localBasePath())) {
            @mkdir($this->config->localBasePath(), 0775, true);
        }
    }

    private function bucketPath(string $bucket): string
    {
        return rtrim($this->config->localBasePath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $bucket;
    }

    public function createBucket(string $bucketName, bool $versioningEnabled = true, array $metadata = []): StorageBucket
    {
        @mkdir($this->bucketPath($bucketName), 0775, true);
        file_put_contents($this->bucketPath($bucketName) . '/.bucket.json', json_encode([
            'versioning' => $versioningEnabled,
            'metadata' => $metadata,
        ], JSON_PRETTY_PRINT));
        return new StorageBucket($bucketName, $versioningEnabled, $metadata);
    }

    public function bucketExists(string $bucketName): bool
    {
        return is_dir($this->bucketPath($bucketName));
    }

    public function listBuckets(): array
    {
        $base = rtrim($this->config->localBasePath(), DIRECTORY_SEPARATOR);
        if (!is_dir($base)) {
            return [];
        }
        $items = array_filter(scandir($base) ?: [], fn ($i) => $i !== '.' && $i !== '..' && is_dir($base . DIRECTORY_SEPARATOR . $i));
        return array_map(fn ($name) => new StorageBucket($name, true), $items);
    }

    public function deleteBucket(string $bucketName): void
    {
        $path = $this->bucketPath($bucketName);
        if (!is_dir($path)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $fileinfo->isDir() ? @rmdir($fileinfo->getPathname()) : @unlink($fileinfo->getPathname());
        }
        @rmdir($path);
    }

    public function uploadObject(string $bucket, string $key, string|\Stringable|array $content, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        @mkdir(dirname($path), 0775, true);
        $bytes = is_array($content) ? implode('', $content) : (string) $content;
        file_put_contents($path, $bytes);
        if (!empty($metadata) || $contentType) {
            file_put_contents($path . '.meta.json', json_encode(['contentType' => $contentType, 'metadata' => $metadata], JSON_PRETTY_PRINT));
        }
        return new StorageObject($bucket, $key, $contentType, strlen($bytes), $metadata);
    }

    public function uploadStream(string $bucket, string $key, \Psr\Http\Message\StreamInterface|resource $stream, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        @mkdir(dirname($path), 0775, true);
        $out = fopen($path, 'wb');
        if ($stream instanceof \Psr\Http\Message\StreamInterface) {
            while (!$stream->eof()) {
                fwrite($out, $stream->read(8192));
            }
        } else {
            stream_copy_to_stream($stream, $out);
        }
        fclose($out);

        if (!empty($metadata) || $contentType) {
            file_put_contents($path . '.meta.json', json_encode(['contentType' => $contentType, 'metadata' => $metadata], JSON_PRETTY_PRINT));
        }

        return new StorageObject($bucket, $key, $contentType, filesize($path), $metadata);
    }

    public function downloadObjectAsBytes(string $bucket, string $key, ?string $versionId = null): string
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        return file_get_contents($path) ?: '';
    }

    public function downloadToStream(string $bucket, string $key, $outputStream, ?string $versionId = null): void
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        $in = fopen($path, 'rb');
        stream_copy_to_stream($in, $outputStream);
        fclose($in);
    }

    public function deleteObject(string $bucket, string $key, ?string $versionId = null): void
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        @unlink($path);
        @unlink($path . '.meta.json');
    }

    public function listObjects(string $bucket, ?string $prefix = null): array
    {
        $path = $this->bucketPath($bucket);
        if (!is_dir($path)) {
            return [];
        }
        $objects = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $rel = ltrim(str_replace($path, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            if ($prefix && !str_starts_with($rel, $prefix)) {
                continue;
            }
            $objects[] = new StorageObject($bucket, $rel, null, $file->getSize());
        }
        return $objects;
    }

    public function listObjectVersions(string $bucket, string $key): array
    {
        $path = $this->bucketPath($bucket) . DIRECTORY_SEPARATOR . $key;
        if (!file_exists($path)) {
            return [];
        }
        return [
            new ObjectVersion($bucket, $key, 'current', filesize($path), new \DateTimeImmutable('@' . filemtime($path))),
        ];
    }

    public function generateSignedUrl(string $bucket, string $key, ?int $ttlSeconds = null): string
    {
        $expires = time() + ($ttlSeconds ?? 3600);
        $signature = hash_hmac('sha256', "{$bucket}/{$key}|{$expires}", 'dev-signing-key');
        return sprintf('/swift/local/%s/%s?e=%d&s=%s', rawurlencode($bucket), rawurlencode($key), $expires, $signature);
    }

    public function verifySignedUrl(string $signedUrl, string $bucket, string $key): bool
    {
        $parts = parse_url($signedUrl);
        if (!isset($parts['query'])) {
            return false;
        }
        parse_str($parts['query'], $q);
        if (!isset($q['e'], $q['s'])) {
            return false;
        }
        if ((int) $q['e'] < time()) {
            return false;
        }
        $expected = hash_hmac('sha256', "{$bucket}/{$key}|{$q['e']}", 'dev-signing-key');
        return hash_equals($expected, $q['s']);
    }
}