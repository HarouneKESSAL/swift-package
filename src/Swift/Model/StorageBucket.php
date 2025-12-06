<?php

declare(strict_types=1);

namespace Swift\Model;

final class StorageBucket
{
    public function __construct(
        public readonly string $name,
        public readonly bool $versioningEnabled,
        public readonly array $metadata = []
    ) {
    }
}