<?php

declare(strict_types=1);

namespace Swift\Storage\Provider;

use Swift\Config\SwiftConfig;
use Swift\Model\ObjectVersion;
use Swift\Model\StorageBucket;
use Swift\Model\StorageObject;
use Swift\Storage\StorageProvider;
use Aws\S3\S3Client;

final class S3Provider implements StorageProvider
{
    private S3Client $client;

    public function __construct(private readonly SwiftConfig $config)
    {
        $s3 = $config->s3() + [
                'version' => 'latest',
                'region' => $config->s3()['region'] ?? 'us-east-1',
                'endpoint' => $config->s3()['endpoint'] ?? null,
                'use_path_style_endpoint' => $config->s3()['use_path_style_endpoint'] ?? false,
                'credentials' => [
                    'key' => $config->s3()['key'] ?? null,
                    'secret' => $config->s3()['secret'] ?? null,
                ],
            ];

        $this->client = new S3Client(array_filter($s3, fn ($v) => $v !== null));
    }

    public function createBucket(string $bucketName, bool $versioningEnabled = true, array $metadata = []): StorageBucket
    {
        $this->client->createBucket(['Bucket' => $bucketName]);
        if ($versioningEnabled) {
            $this->client->putBucketVersioning([
                'Bucket' => $bucketName,
                'VersioningConfiguration' => ['Status' => 'Enabled'],
            ]);
        }
        return new StorageBucket($bucketName, $versioningEnabled, $metadata);
    }

    public function bucketExists(string $bucketName): bool
    {
        try {
            $this->client->headBucket(['Bucket' => $bucketName]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function listBuckets(): array
    {
        $res = $this->client->listBuckets();
        $buckets = [];
        foreach ($res['Buckets'] ?? [] as $b) {
            $buckets[] = new StorageBucket($b['Name'], true);
        }
        return $buckets;
    }

    public function deleteBucket(string $bucketName): void
    {
        $this->client->deleteBucket(['Bucket' => $bucketName]);
    }

    public function uploadObject(string $bucket, string $key, string|\Stringable|array $content, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $bytes = is_array($content) ? implode('', $content) : (string) $content;
        $this->client->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $bytes,
            'ContentType' => $contentType ?? 'application/octet-stream',
            'Metadata' => $metadata,
        ]);
        return new StorageObject($bucket, $key, $contentType, strlen($bytes), $metadata);
    }

    public function uploadStream(string $bucket, string $key, \Psr\Http\Message\StreamInterface|resource $stream, ?string $contentType = null, array $metadata = []): StorageObject
    {
        $body = $stream instanceof \Psr\Http\Message\StreamInterface ? $stream : $stream;
        $this->client->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => $body,
            'ContentType' => $contentType ?? 'application/octet-stream',
            'Metadata' => $metadata,
        ]);
        return new StorageObject($bucket, $key, $contentType, null, $metadata);
    }

    public function downloadObjectAsBytes(string $bucket, string $key, ?string $versionId = null): string
    {
        $params = ['Bucket' => $bucket, 'Key' => $key];
        if ($versionId) {
            $params['VersionId'] = $versionId;
        }
        $res = $this->client->getObject($params);
        return (string) $res['Body'];
    }

    public function downloadToStream(string $bucket, string $key, $outputStream, ?string $versionId = null): void
    {
        $params = ['Bucket' => $bucket, 'Key' => $key];
        if ($versionId) {
            $params['VersionId'] = $versionId;
        }
        $res = $this->client->getObject($params);
        stream_copy_to_stream($res['Body']->detach(), $outputStream);
    }

    public function deleteObject(string $bucket, string $key, ?string $versionId = null): void
    {
        $params = ['Bucket' => $bucket, 'Key' => $key];
        if ($versionId) {
            $params['VersionId'] = $versionId;
        }
        $this->client->deleteObject($params);
    }

    public function listObjects(string $bucket, ?string $prefix = null): array
    {
        $res = $this->client->listObjectsV2([
            'Bucket' => $bucket,
            'Prefix' => $prefix,
        ]);
        $objects = [];
        foreach ($res['Contents'] ?? [] as $o) {
            $objects[] = new StorageObject($bucket, $o['Key'], null, (int) $o['Size']);
        }
        return $objects;
    }

    public function listObjectVersions(string $bucket, string $key): array
    {
        $res = $this->client->listObjectVersions([
            'Bucket' => $bucket,
            'Prefix' => $key,
        ]);
        $versions = [];
        foreach ($res['Versions'] ?? [] as $v) {
            if (($v['Key'] ?? '') !== $key) {
                continue;
            }
            $versions[] = new ObjectVersion(
                $bucket,
                $key,
                (string) $v['VersionId'],
                (int) $v['Size'],
                new \DateTimeImmutable($v['LastModified'])
            );
        }
        return $versions;
    }

    public function generateSignedUrl(string $bucket, string $key, ?int $ttlSeconds = null): string
    {
        $cmd = $this->client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
        $req = $this->client->createPresignedRequest($cmd, sprintf('+%d seconds', $ttlSeconds ?? 3600));
        return (string) $req->getUri();
    }

    public function verifySignedUrl(string $signedUrl, string $bucket, string $key): bool
    {
        return true;
    }
}