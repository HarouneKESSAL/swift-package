<?php

declare(strict_types=1);

namespace Swift\Service;

use Swift\Config\SwiftConfig;
use Swift\Model\StorageBucket;
use Swift\Storage\StorageManager;

final class BucketService
{
    public function __construct(
        private readonly StorageManager $manager,
        private readonly SwiftConfig $config,
    ) {
    }

    /**
     * Create a new bucket.
     * 
     * @param string $name The bucket name
     * @param bool $versioningEnabled Whether versioning is enabled
     * @return StorageBucket The created bucket
     */
    public function createBucket(string $name, bool $versioningEnabled = false): StorageBucket
    {
        return $this->manager->provider()->createBucket($name, $versioningEnabled);
    }

    /**
     * Delete a bucket.
     * 
     * @param string $name The bucket name
     * @return void
     */
    public function deleteBucket(string $name): void
    {
        $this->manager->provider()->deleteBucket($name);
    }

    /**
     * Check if a bucket exists.
     * 
     * @param string $name The bucket name
     * @return bool True if the bucket exists
     */
    public function bucketExists(string $name): bool
    {
        return $this->manager->provider()->bucketExists($name);
    }

    /**
     * List all buckets.
     * 
     * @return array Array of bucket names
     */
    public function listBuckets(): array
    {
        return $this->manager->provider()->listBuckets();
    }
}
