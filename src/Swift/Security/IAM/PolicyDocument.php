<?php

declare(strict_types=1);

namespace Swift\Security\IAM;

final class PolicyDocument
{
    /** @param PolicyStatement[] $statements */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $statements
    ) {
    }
}