<?php

declare(strict_types=1);

namespace Swift\Security\IAM;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class IamMiddleware
{
    /** @var callable(Request): array */
    public function __construct(
        private readonly PolicyEngine $engine,
        private readonly callable $policyLoader
    ) {
    }

    public function handle(Request $request, Closure $next, string $action, string $resourceResolver): mixed
    {
        $documents = ($this->policyLoader)($request);
        $resourceArn = $this->resolveResource($request, $resourceResolver);

        $allowed = $this->engine->isAllowed($documents, $action, $resourceArn);
        if (!$allowed) {
            throw new HttpException(403, 'Access denied by Swift IAM policy');
        }

        return $next($request);
    }

    private function resolveResource(Request $req, string $resolver): string
    {
        // Resolver format: "bucket:{param}" or "object:{bucketParam}:{keyParam}"
        $parts = explode(':', $resolver);
        if ($parts[0] === 'bucket') {
            $tenant = (string) ($req->header('X-Tenant') ?? 'default');
            $bucket = (string) $req->route($parts[1]);
            return ResourceArn::bucket($tenant, $bucket);
        }
        if ($parts[0] === 'object') {
            $tenant = (string) ($req->header('X-Tenant') ?? 'default');
            $bucket = (string) $req->route($parts[1]);
            $key = (string) $req->route($parts[2]);
            return ResourceArn::object($tenant, $bucket, $key);
        }
        return ResourceArn::all();
    }
}