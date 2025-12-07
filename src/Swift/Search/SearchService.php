<?php

declare(strict_types=1);

namespace Swift\Search;

use Swift\Config\SwiftConfig;
use Swift\Model\StorageObject;

final class SearchService
{
    private bool $enabled;
    private ?SearchAdapter $adapter = null;

    public function __construct(SwiftConfig $config)
    {
        $searchConfig = $config->search();
        $this->enabled = (bool) ($searchConfig['enabled'] ?? false);
        
        if ($this->enabled) {
            // Support both 'driver' and 'provider' config keys
            $provider = $searchConfig['driver'] ?? $searchConfig['provider'] ?? 'meilisearch';
            
            if ($provider === 'meilisearch') {
                $this->adapter = new MeilisearchAdapter($searchConfig['meilisearch'] ?? []);
            } else {
                throw new \RuntimeException("Unsupported search provider: {$provider}");
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Index an object for searching.
     * 
     * @param StorageObject $object The object to index
     * @return bool True if indexing was successful
     */
    public function indexObject(StorageObject $object): bool
    {
        if (!$this->enabled || $this->adapter === null) {
            return false;
        }

        return $this->adapter->indexObject($object);
    }

    /**
     * Remove an object from the search index.
     * 
     * @param string $bucket The bucket name
     * @param string $key The object key
     * @return bool True if removal was successful
     */
    public function removeObject(string $bucket, string $key): bool
    {
        if (!$this->enabled || $this->adapter === null) {
            return false;
        }

        return $this->adapter->removeObject($bucket, $key);
    }

    /**
     * Search for objects.
     * 
     * @param string $query The search query
     * @param array $filters Optional filters (e.g., ['bucket' => 'my-bucket'])
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of search results
     */
    public function search(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        if (!$this->enabled || $this->adapter === null) {
            return [];
        }

        return $this->adapter->search($query, $filters, $limit, $offset);
    }

    /**
     * Update the metadata of an indexed object.
     * 
     * @param string $bucket The bucket name
     * @param string $key The object key
     * @param array $metadata The new metadata
     * @return bool True if update was successful
     */
    public function updateObjectMetadata(string $bucket, string $key, array $metadata): bool
    {
        if (!$this->enabled || $this->adapter === null) {
            return false;
        }

        return $this->adapter->updateObjectMetadata($bucket, $key, $metadata);
    }

    /**
     * Clear all indexed objects.
     * 
     * @return bool True if clearing was successful
     */
    public function clearIndex(): bool
    {
        if (!$this->enabled || $this->adapter === null) {
            return false;
        }

        return $this->adapter->clearIndex();
    }
}
