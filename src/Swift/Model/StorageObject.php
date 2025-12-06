<?php

declare(strict_types=1);

namespace Swift\Model;

final class StorageObject
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
        public readonly ?string $contentType = null,
        public readonly ?int $size = null,
        public readonly array $metadata = [],
        public readonly ?string $versionId = null
    ) {
    }
}