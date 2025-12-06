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
];