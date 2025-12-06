# Swift Storage Library (Laravel Package)

A professional, production-grade storage library for Laravel applications with support for object storage, versioning, caching, IAM-style access control, event notifications, CDN support, and search capabilities.

## Features

- Object Storage: upload, download, delete, list
- Buckets: logical groupings with configurable settings
- Object Versioning: provider-aware support (S3 native, local emulated)
- Streaming: efficient streams for large files
- Metadata Management
- Object Key Strategy: `uuid`, `client`, `pattern`
- Storage Providers: Local, AWS S3, MinIO (S3-compatible)
- Advanced: Caching, IAM-style access control, Events, CDN signed URLs, Encryption, Search
- Observability: Metrics, correlation IDs, audit logging
- Enterprise: Rate limiting, idempotency, multi-tenancy
- API Docs: OpenAPI via L5-Swagger (planned)

## Installation

```bash
composer require harounekessal/swift-package
```

Publish config:

```bash
php artisan vendor:publish --tag=swift-config
```

## Configuration

File: `config/swift.php`

```php
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
            'endpoint' => env('SWIFT_S3_ENDPOINT'), // optional
            'use_path_style_endpoint' => env('SWIFT_S3_PATH_STYLE', false),
        ],
        'versioning_enabled' => env('SWIFT_VERSIONING_ENABLED', true),
        'stream_chunk_size' => env('SWIFT_STREAM_CHUNK_SIZE', 5 * 1024 * 1024),
        'key_strategy' => env('SWIFT_KEY_STRATEGY', 'client'), // uuid, client, pattern
        'key_pattern' => env('SWIFT_KEY_PATTERN', '{bucket}/{uuid}'),
    ],
];
```

## Usage

```php
use Swift\Service\BucketService;
use Swift\Service\StorageService;

public function demo(BucketService $bucketService, StorageService $storageService)
{
    $bucketService->createBucket('my-bucket', true);
    $storageService->uploadObject('my-bucket', 'hello.txt', 'Hello, World!', 'text/plain');
    $bytes = $storageService->downloadObjectAsBytes('my-bucket', 'hello.txt');
}
```

## Roadmap

- Caching (array/Redis) for metadata/content
- IAM policy engine (Allow/Deny with actions/resources)
- Event notifications (Redis Streams, Kafka, RabbitMQ)
- CDN signed URLs (HMAC-SHA256, expiration, optional client IP binding)
- Encryption (AES-256-GCM + envelope keys, rotation)
- Search (Meilisearch + Laravel Scout)
- Observability (Prometheus/OpenTelemetry + audit logs)
- REST example app in `examples/` with routes and controllers
- OpenAPI via L5-Swagger

## License

Apache License 2.0