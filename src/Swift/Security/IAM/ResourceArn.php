<?php

declare(strict_types=1);

namespace Swift\Security\IAM;

final class ResourceArn
{
    // Format: swift:{tenant}:{bucket}/{objectKey}
    public static function bucket(string $tenant, string $bucket): string
    {
        return "swift:{$tenant}:{$bucket}/*";
    }

    public static function object(string $tenant, string $bucket, string $key): string
    {
        return "swift:{$tenant}:{$bucket}/{$key}";
    }

    public static function tenantAll(string $tenant): string
    {
        return "swift:{$tenant}:*";
    }

    public static function all(): string
    {
        return "swift:*:*";
    }
}