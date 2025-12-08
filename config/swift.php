<?php

return [
    'storage' => [
        'provider' => env('SWIFT_STORAGE_PROVIDER', 'local'), // local, s3, minio
        'local' => [
            'base_path' => env('SWIFT_LOCAL_BASE_PATH', storage_path('app/swift')),
        ],
        's3' => [
            'region' => env('SWIFT_S3_REGION', 'us-east-1'),
            'bucket' => env('SWIFT_S3_BUCKET', 'swift'),
            'key' => env('SWIFT_S3_KEY'),
            'secret' => env('SWIFT_S3_SECRET'),
            'endpoint' => env('SWIFT_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('SWIFT_S3_PATH_STYLE', false),
        ],
        'versioning_enabled' => env('SWIFT_VERSIONING_ENABLED', true),
        'stream_chunk_size' => env('SWIFT_STREAM_CHUNK_SIZE', 5 * 1024 * 1024),
        'key_strategy' => env('SWIFT_KEY_STRATEGY', 'client'), // uuid, client, pattern
        'key_pattern' => env('SWIFT_KEY_PATTERN', '{bucket}/{uuid}'),
    ],

    'cache' => [
        'enabled' => env('SWIFT_CACHE_ENABLED', true),
        'type' => env('SWIFT_CACHE_TYPE', 'array'), // array, redis
        'cache_metadata' => env('SWIFT_CACHE_METADATA', true),
        'cache_content' => env('SWIFT_CACHE_CONTENT', false),
        'max_content_cache_size' => env('SWIFT_CACHE_MAX_CONTENT_SIZE', 10 * 1024 * 1024), // 10MB
        'metadata_ttl' => env('SWIFT_CACHE_METADATA_TTL', '30m'),
        'content_ttl' => env('SWIFT_CACHE_CONTENT_TTL', '10m'),
        'redis' => [
            'connection' => env('SWIFT_CACHE_REDIS_CONNECTION', 'default'),
            'key_prefix' => env('SWIFT_CACHE_REDIS_PREFIX', 'swift:cache:'),
        ],
    ],

    'access' => [
        'enabled' => env('SWIFT_ACCESS_ENABLED', false),
        'method' => env('SWIFT_ACCESS_METHOD', 'jwt'), // jwt, oauth2 (planned)
        'jwt' => [
            'secret' => env('SWIFT_JWT_SECRET', 'change-me'),
            'token_validity_seconds' => env('SWIFT_JWT_TTL', 3600),
            'issuer' => env('SWIFT_JWT_ISSUER', 'swift'),
        ],
        'rbac' => [
            'enabled' => env('SWIFT_RBAC_ENABLED', true),
            'default_role' => env('SWIFT_RBAC_DEFAULT_ROLE', 'USER'),
            'admin_role' => env('SWIFT_RBAC_ADMIN_ROLE', 'ADMIN'),
        ],
    ],

    'encryption' => [
        'enabled' => env('SWIFT_ENCRYPTION_ENABLED', false),
        'algorithm' => env('SWIFT_ENCRYPTION_ALGORITHM', 'aes-256-gcm'),
        'master_key' => env('SWIFT_ENCRYPTION_MASTER_KEY', ''), // base64-encoded 256-bit key
        'key_rotation_days' => env('SWIFT_ENCRYPTION_KEY_ROTATION_DAYS', 90),
    ],

    'cdn' => [
        'enabled' => env('SWIFT_CDN_ENABLED', false),
        'base_url' => env('SWIFT_CDN_BASE_URL', ''),
        'url' => [
            'signing_key' => env('SWIFT_CDN_SIGNING_KEY', env('SWIFT_CDN_SECRET', '')), // HMAC signing key
            'expiration' => env('SWIFT_CDN_URL_EXPIRATION', 3600), // default TTL in seconds
            'algorithm' => env('SWIFT_CDN_URL_ALGORITHM', 'sha256'), // HMAC algorithm
            'include_client_ip' => env('SWIFT_CDN_INCLUDE_CLIENT_IP', false), // IP binding
        ],
        // @deprecated Legacy config keys - use 'url' nested keys instead
        'secret' => env('SWIFT_CDN_SECRET', ''),
        'default_ttl_seconds' => env('SWIFT_CDN_DEFAULT_TTL', 3600),
        'bind_ip' => env('SWIFT_CDN_BIND_IP', false),
    ],

    'search' => [
        'enabled' => env('SWIFT_SEARCH_ENABLED', false),
        'driver' => env('SWIFT_SEARCH_DRIVER', 'meilisearch'), // search driver
        'provider' => env('SWIFT_SEARCH_PROVIDER', 'meilisearch'), // @deprecated Use 'driver' instead
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', env('SWIFT_SEARCH_MEILISEARCH_HOST', 'http://localhost:7700')),
            'key' => env('MEILISEARCH_KEY', env('SWIFT_SEARCH_MEILISEARCH_API_KEY', '')),
            'api_key' => env('SWIFT_SEARCH_MEILISEARCH_API_KEY', ''), // @deprecated Use 'key' instead
            'index' => env('MEILISEARCH_INDEX', env('SWIFT_SEARCH_MEILISEARCH_INDEX', 'swift_objects')),
        ],
    ],
];