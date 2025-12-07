<?php

declare(strict_types=1);

namespace Swift\Caching;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class SwiftCache
{
    public function __construct(
        private readonly CacheRepository $repo,
        private readonly string $prefix,
        private readonly bool $cacheMetadata,
        private readonly bool $cacheContent,
        private readonly int $maxContentSize,
        private readonly int $metadataTtlSeconds,
        private readonly int $contentTtlSeconds,
    ) {
    }

    public function getMetadata(string $bucket, string $key): ?array
    {
        if (!$this->cacheMetadata) {
            return null;
        }
        return $this->repo->get($this->prefix . "meta:{$bucket}:{$key}");
    }

    public function putMetadata(string $bucket, string $key, array $metadata): void
    {
        if (!$this->cacheMetadata) {
            return;
        }
        $this->repo->put($this->prefix . "meta:{$bucket}:{$key}", $metadata, $this->metadataTtlSeconds);
    }

    public function getContent(string $bucket, string $key): ?string
    {
        if (!$this->cacheContent) {
            return null;
        }
        return $this->repo->get($this->prefix . "content:{$bucket}:{$key}");
    }

    public function putContent(string $bucket, string $key, string $content): void
    {
        if (!$this->cacheContent || strlen($content) > $this->maxContentSize) {
            return;
        }
        $this->repo->put($this->prefix . "content:{$bucket}:{$key}", $content, $this->contentTtlSeconds);
    }

    public function invalidateObject(string $bucket, string $key): void
    {
        $this->repo->forget($this->prefix . "meta:{$bucket}:{$key}");
        $this->repo->forget($this->prefix . "content:{$bucket}:{$key}");
    }
}