<?php

declare(strict_types=1);

namespace Swift\Storage;

use Swift\Model\ObjectVersion;
use Swift\Model\StorageBucket;
use Swift\Model\StorageObject;

interface StorageProvider
{
    // Buckets
    public function createBucket(string $bucketName, bool $versioningEnabled = true, array $metadata = []): StorageBucket;
    public function bucketExists(string $bucketName): bool;
    public function listBuckets(): array;
    public function deleteBucket(string $bucketName): void;

    // Objects
    public function uploadObject(string $bucket, string $key, string|\Stringable|array $content, ?string $contentType = null, array $metadata = []): StorageObject;
    public function uploadStream(string $bucket, string $key, \Psr\Http\Message\StreamInterface|resource $stream, ?string $contentType = null, array $metadata = []): StorageObject;
    public function downloadObjectAsBytes(string $bucket, string $key, ?string $versionId = null): string;
    public function downloadToStream(string $bucket, string $key, $outputStream, ?string $versionId = null): void;
    public function deleteObject(string $bucket, string $key, ?string $versionId = null): void;
    public function listObjects(string $bucket, ?string $prefix = null): array;
    public function listObjectVersions(string $bucket, string $key): array;

    // Signed URLs
    public function generateSignedUrl(string $bucket, string $key, ?int $ttlSeconds = null): string;
    public function verifySignedUrl(string $signedUrl, string $bucket, string $key): bool;
}