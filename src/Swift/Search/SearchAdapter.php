<?php

declare(strict_types=1);

namespace Swift\Search;

use Swift\Model\StorageObject;

interface SearchAdapter
{
    /**
     * Index an object for searching.
     * 
     * @param StorageObject $object The object to index
     * @return bool True if indexing was successful
     */
    public function indexObject(StorageObject $object): bool;

    /**
     * Remove an object from the search index.
     * 
     * @param string $bucket The bucket name
     * @param string $key The object key
     * @return bool True if removal was successful
     */
    public function removeObject(string $bucket, string $key): bool;

    /**
     * Search for objects.
     * 
     * @param string $query The search query
     * @param array $filters Optional filters
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array Array of search results
     */
    public function search(string $query, array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Update the metadata of an indexed object.
     * 
     * @param string $bucket The bucket name
     * @param string $key The object key
     * @param array $metadata The new metadata
     * @return bool True if update was successful
     */
    public function updateObjectMetadata(string $bucket, string $key, array $metadata): bool;

    /**
     * Clear all indexed objects.
     * 
     * @return bool True if clearing was successful
     */
    public function clearIndex(): bool;
}
