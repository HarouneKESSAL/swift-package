<?php

declare(strict_types=1);

namespace Swift\Model;

final class ObjectVersion
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
        public readonly string $versionId,
        public readonly int $size,
        public readonly \DateTimeImmutable $createdAt
    ) {
    }
}