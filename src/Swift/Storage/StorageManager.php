<?php

declare(strict_types=1);

namespace Swift\Storage;

use Swift\Config\SwiftConfig;
use Swift\Storage\Provider\LocalProvider;
use Swift\Storage\Provider\S3Provider;

final class StorageManager
{
    public function __construct(private readonly SwiftConfig $config)
    {
    }

    public function provider(): StorageProvider
    {
        return match ($this->config->provider()) {
            'local' => new LocalProvider($this->config),
            's3', 'minio' => new S3Provider($this->config),
            default => new LocalProvider($this->config),
        };
    }
}