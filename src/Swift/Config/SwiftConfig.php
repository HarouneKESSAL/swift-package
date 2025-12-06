<?php

declare(strict_types=1);

namespace Swift\Config;

final class SwiftConfig
{
    public function __construct(private array $config)
    {
    }

    public function provider(): string
    {
        return $this->config['storage']['provider'] ?? 'local';
    }

    public function localBasePath(): string
    {
        return $this->config['storage']['local']['base_path'] ?? storage_path('app/swift');
    }

    public function s3(): array
    {
        return $this->config['storage']['s3'] ?? [];
    }

    public function minio(): array
    {
        return $this->config['storage']['minio'] ?? [];
    }

    public function versioningEnabled(): bool
    {
        return (bool) ($this->config['storage']['versioning_enabled'] ?? true);
    }

    public function streamChunkSize(): int
    {
        return (int) ($this->config['storage']['stream_chunk_size'] ?? 5 * 1024 * 1024);
    }

    public function keyStrategy(): string
    {
        return $this->config['storage']['key_strategy'] ?? 'client';
    }

    public function keyPattern(): string
    {
        return $this->config['storage']['key_pattern'] ?? '{bucket}/{uuid}';
    }
}