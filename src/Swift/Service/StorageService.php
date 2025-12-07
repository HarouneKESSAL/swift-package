<?php

declare(strict_types=1);

namespace Swift\Service;

use Swift\Config\SwiftConfig;
use Swift\Caching\CacheManager;
use Swift\Caching\SwiftCache;
use Swift\Model\ObjectVersion;
use Swift\Model\StorageObject;
use Swift\Storage\StorageManager;
use Swift\Search\SearchService;

final class StorageService
{
    private ?SwiftCache $cache = null;
    private ?SearchService $search = null;

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
        
        // Initialize search service if enabled
        $searchCfg = $this->configData('search');
        if (($searchCfg['enabled'] ?? false) === true) {
            $this->search = new SearchService($this->config);
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
        // Index object for search if enabled
        $this->search?->indexObject($obj);
        return $obj;
    }

    public function uploadStream(string $bucket, string $key, \Psr\Http\Message\StreamInterface|resource $stream, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $obj = $this->manager->provider()->uploadStream($bucket, $key, $stream, $contentType, $metadata);
        $this->cache?->invalidateObject($bucket, $key);
        if (!empty($metadata)) {
            $this->cache?->putMetadata($bucket, $key, $metadata);
        }
        // Index object for search if enabled
        $this->search?->indexObject($obj);
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
        // Remove from search index if enabled
        $this->search?->removeObject($bucket, $key);
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

    /**
     * Search for objects using the configured search service.
     * 
     * @param string $query Search query
     * @param string|null $bucket Optional bucket filter
     * @param string|null $prefix Optional prefix filter
     * @param array $metadataFilter Optional metadata filters
     * @return array Array of search results
     */
    public function searchObjects(string $query, ?string $bucket = null, ?string $prefix = null, array $metadataFilter = []): array
    {
        if ($this->search === null) {
            return [];
        }

        $filters = [];
        
        if ($bucket !== null) {
            $filters['bucket'] = $bucket;
        }
        
        // Note: prefix filtering would need to be implemented in the search adapter
        // For now, we'll rely on the search adapter's capabilities
        
        // Add metadata filters
        foreach ($metadataFilter as $key => $value) {
            $filters["metadata.{$key}"] = $value;
        }

        return $this->search->search($query, $filters);
    }
}