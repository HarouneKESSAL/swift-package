<?php

declare(strict_types=1);

namespace Swift\Security\IAM;

final class PolicyStatement
{
    public const EFFECT_ALLOW = 'ALLOW';
    public const EFFECT_DENY = 'DENY';

    public function __construct(
        public readonly string $effect,
        /** @var string[] */
        public readonly array $actions,
        /** @var string[] */
        public readonly array $resources,
        public readonly ?string $condition = null,
    ) {
    }
}