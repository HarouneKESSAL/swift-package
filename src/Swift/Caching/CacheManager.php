<?php

declare(strict_types=1);

namespace Swift\Caching;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

final class CacheManager
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function repo(): CacheRepository
    {
        $type = $this->config['type'] ?? 'array';
        if ($type === 'redis') {
            $conn = $this->config['redis']['connection'] ?? 'default';
            return Cache::store('redis')->setConnection($conn);
        }

        return Cache::store('array');
    }

    public function prefix(): string
    {
        $type = $this->config['type'] ?? 'array';
        if ($type === 'redis') {
            return (string) ($this->config['redis']['key_prefix'] ?? 'swift:cache:');
        }
        return 'swift:cache:';
    }

    public function ttlSeconds(string $ttl): int
    {
        // parse short ttl strings like "30m" or "10m", "1h"
        if (preg_match('/^(\d+)\s*([smhd])$/i', $ttl, $m)) {
            $n = (int) $m[1];
            return match (strtolower($m[2])) {
                's' => $n,
                'm' => $n * 60,
                'h' => $n * 3600,
                'd' => $n * 86400,
                default => $n,
            };
        }
        return (int) $ttl;
    }
}