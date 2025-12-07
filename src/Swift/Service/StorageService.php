<?php

declare(strict_types=1);

namespace Swift\Service;

use Swift\Config\SwiftConfig;
use Swift\Caching\CacheManager;
use Swift\Caching\SwiftCache;
use Swift\Model\ObjectVersion;
use Swift\Model\StorageObject;
use Swift\Storage\StorageManager;

final class StorageService
{
    private ?SwiftCache $cache = null;

    public function __construct(
        private readonly StorageManager $manager,
        private readonly SwiftConfig $config,
    ) {
        $cacheCfg = $this->configData('cache');
        if (($cacheCfg['enabled'] ?? false) === true) {
            $cm = new CacheManager($cacheCfg);
            $this->cache = new SwiftCache(
                repo: $cm->repo(),
                prefix: $cm->prefix(),
                cacheMetadata: (bool) ($cacheCfg['cache_metadata'] ?? true),
                cacheContent: (bool) ($cacheCfg['cache_content'] ?? false),
                maxContentSize: (int) ($cacheCfg['max_content_cache_size'] ?? 10 * 1024 * 1024),
                metadataTtlSeconds: $cm->ttlSeconds((string) ($cacheCfg['metadata_ttl'] ?? '30m')),
                contentTtlSeconds: $cm->ttlSeconds((string) ($cacheCfg['content_ttl'] ?? '10m')),
            );
        }
    }

    private function configData(string $key): array
    {
        $ref = new \ReflectionClass($this->config);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $data = $prop->getValue($this->config);
        return (array) ($data[$key] ?? []);
    }

    public function uploadObject(string $bucket, string $key, string|array $content, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $obj = $this->manager->provider()->uploadObject($bucket, $key, $content, $contentType, $metadata);
        $this->cache?->invalidateObject($bucket, $key);
        if (!empty($metadata)) {
            $this->cache?->putMetadata($bucket, $key, $metadata);
        }
        if (is_string($content)) {
            $this->cache?->putContent($bucket, $key, $content);
        }
        return $obj;
    }

    public function uploadStream(string $bucket, string $key, \Psr\Http\Message\StreamInterface|resource $stream, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $obj = $this->manager->provider()->uploadStream($bucket, $key, $stream, $contentType, $metadata);
        $this->cache?->invalidateObject($bucket, $key);
        if (!empty($metadata)) {
            $this->cache?->putMetadata($bucket, $key, $metadata);
        }
        return $obj;
    }

    public function downloadObjectAsBytes(string $bucket, string $key, ?string $versionId = null): string
    {
        $cached = $this->cache?->getContent($bucket, $key);
        if ($cached !== null) {
            return $cached;
        }
        $bytes = $this->manager->provider()->downloadObjectAsBytes($bucket, $key, $versionId);
        $this->cache?->putContent($bucket, $key, $bytes);
        return $bytes;
    }

    public function downloadObject(string $bucket, string $key, $outputStream, ?string $versionId = null): void
    {
        $this->manager->provider()->downloadToStream($bucket, $key, $outputStream, $versionId);
    }

    public function listObjects(string $bucket, ?string $prefix = null): array
    {
        return $this->manager->provider()->listObjects($bucket, $prefix);
    }

    public function deleteObject(string $bucket, string $key, ?string $versionId = null): void
    {
        $this->manager->provider()->deleteObject($bucket, $key, $versionId);
        $this->cache?->invalidateObject($bucket, $key);
    }

    public function listObjectVersions(string $bucket, string $key): array
    {
        return $this->manager->provider()->listObjectVersions($bucket, $key);
    }

    public function generateSignedUrl(string $bucket, string $key, ?int $ttlSeconds = null): string
    {
        return $this->manager->provider()->generateSignedUrl($bucket, $key, $ttlSeconds);
    }

    public function verifySignedUrl(string $signedUrl, string $bucket, string $key): bool
    {
        return $this->manager->provider()->verifySignedUrl($signedUrl, $bucket, $key);
    }
}