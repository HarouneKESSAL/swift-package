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

## Advanced Features

### Encryption

Protect your data at rest with AES-256-GCM encryption and envelope key management. The encryption service uses envelope encryption where data is encrypted with a randomly generated Data Encryption Key (DEK), and the DEK itself is encrypted with the master key.

#### Usage Example

```php
use Swift\Encryption\EncryptionService;

public function demo(EncryptionService $encryption)
{
    // Encrypt sensitive data
    // Returns an array with: ciphertext, data_key, iv, tag, key_iv, key_tag
    $encrypted = $encryption->encrypt('sensitive data');
    
    // Decrypt when needed
    $plaintext = $encryption->decrypt($encrypted);
    
    // Rotate encryption keys (re-encrypt with a new DEK)
    $reencrypted = $encryption->rotateKey($encrypted);
}
```

#### Configuration

Set the following environment variables in your `.env` file:

```env
SWIFT_ENCRYPTION_ENABLED=true
SWIFT_ENCRYPTION_ALGORITHM=aes-256-gcm
SWIFT_ENCRYPTION_MASTER_KEY=your-base64-encoded-256-bit-key-here
SWIFT_ENCRYPTION_KEY_ROTATION_DAYS=90
```

In `config/swift.php`:

```php
'encryption' => [
    'enabled' => env('SWIFT_ENCRYPTION_ENABLED', false),
    'algorithm' => env('SWIFT_ENCRYPTION_ALGORITHM', 'aes-256-gcm'),
    'master_key' => env('SWIFT_ENCRYPTION_MASTER_KEY', ''), // base64-encoded 256-bit key
    'key_rotation_days' => env('SWIFT_ENCRYPTION_KEY_ROTATION_DAYS', 90),
],
```

**Note:** Generate a secure master key using: `openssl rand -base64 32`

### CDN Signed URLs

Generate secure, time-limited URLs with HMAC-SHA256 signatures for CDN delivery. Supports optional client IP binding for additional security.

#### Usage Example

```php
use Swift\CDN\CdnService;

public function demo(CdnService $cdn)
{
    // Generate a signed URL (expires in 1 hour)
    $url = $cdn->generateSignedUrl('bucket/file.jpg', 3600);
    // Result: https://cdn.example.com/bucket/file.jpg?expires=1234567890&signature=abc123...
    
    // Generate with IP binding for extra security
    $clientIp = request()->ip(); // or '192.168.1.1'
    $url = $cdn->generateSignedUrl('bucket/file.jpg', 3600, $clientIp);
    
    // Verify a signed URL (server-side)
    $isValid = $cdn->verifySignedUrl($url);
    
    // Verify with IP binding
    $isValid = $cdn->verifySignedUrl($url, $clientIp);
    
    // Invalidate CDN cache for a specific path
    $cdn->invalidate('bucket/file.jpg');
}
```

#### Configuration

Set the following environment variables in your `.env` file:

```env
SWIFT_CDN_ENABLED=true
SWIFT_CDN_BASE_URL=https://cdn.example.com
SWIFT_CDN_SIGNING_KEY=your-secure-random-signing-key
SWIFT_CDN_URL_EXPIRATION=3600
SWIFT_CDN_URL_ALGORITHM=sha256
SWIFT_CDN_INCLUDE_CLIENT_IP=false
```

In `config/swift.php`:

```php
'cdn' => [
    'enabled' => env('SWIFT_CDN_ENABLED', false),
    'base_url' => env('SWIFT_CDN_BASE_URL', ''),
    'url' => [
        'signing_key' => env('SWIFT_CDN_SIGNING_KEY', ''), // HMAC signing key
        'expiration' => env('SWIFT_CDN_URL_EXPIRATION', 3600), // default TTL in seconds
        'algorithm' => env('SWIFT_CDN_URL_ALGORITHM', 'sha256'), // HMAC algorithm
        'include_client_ip' => env('SWIFT_CDN_INCLUDE_CLIENT_IP', false), // IP binding
    ],
],
```

**Note:** Generate a secure signing key using: `openssl rand -hex 32`

### Search (Meilisearch)

Index and search your storage objects using Meilisearch. Objects are automatically indexed on upload and removed from the index on deletion when search is enabled.

#### Usage Example

```php
use Swift\Service\StorageService;
use Swift\Search\SearchService;

public function demo(StorageService $storage, SearchService $search)
{
    // Upload an object (automatically indexed if search is enabled)
    $storage->uploadObject(
        'my-bucket',
        'documents/report.pdf',
        $fileContent,
        'application/pdf',
        ['author' => 'John Doe', 'category' => 'reports']
    );
    
    // Search using StorageService (recommended)
    $results = $storage->searchObjects('quarterly report');
    
    // Search with bucket filter
    $results = $storage->searchObjects('report', bucket: 'my-bucket');
    
    // Search with metadata filters
    $results = $storage->searchObjects(
        'report',
        bucket: 'my-bucket',
        metadataFilter: ['category' => 'reports']
    );
    
    // Direct use of SearchService for advanced operations
    $results = $search->search('query text', ['bucket' => 'my-bucket'], 20, 0);
    
    // Update object metadata in the index
    $search->updateObjectMetadata('bucket', 'key', ['tag' => 'important']);
    
    // Manually remove from index (automatically done on delete)
    $search->removeObject('bucket', 'key');
    
    // Clear the entire index
    $search->clearIndex();
}
```

#### Configuration

Set the following environment variables in your `.env` file:

```env
SWIFT_SEARCH_ENABLED=true
SWIFT_SEARCH_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-meilisearch-master-key
MEILISEARCH_INDEX=swift_objects
```

In `config/swift.php`:

```php
'search' => [
    'enabled' => env('SWIFT_SEARCH_ENABLED', false),
    'driver' => env('SWIFT_SEARCH_DRIVER', 'meilisearch'),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY', ''),
        'index' => env('MEILISEARCH_INDEX', 'swift_objects'),
    ],
],
```

**Note:** Ensure Meilisearch is running and accessible. You can start it with Docker:
```bash
docker run -d -p 7700:7700 getmeili/meilisearch:latest
```

## Roadmap

- ✅ Caching (array/Redis) for metadata/content
- ✅ IAM policy engine (Allow/Deny with actions/resources)
- Event notifications (Redis Streams, Kafka, RabbitMQ)
- ✅ CDN signed URLs (HMAC-SHA256, expiration, optional client IP binding)
- ✅ Encryption (AES-256-GCM + envelope keys, rotation)
- ✅ Search (Meilisearch integration)
- Observability (Prometheus/OpenTelemetry + audit logs)
- REST example app in `examples/` with routes and controllers
- OpenAPI via L5-Swagger

## License

Apache License 2.0