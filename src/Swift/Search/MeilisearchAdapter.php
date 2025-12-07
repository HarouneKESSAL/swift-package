<?php

declare(strict_types=1);

namespace Swift\Search;

use Swift\Model\StorageObject;

final class MeilisearchAdapter implements SearchAdapter
{
    private const INDEX_NAME = 'swift_objects';
    
    private string $host;
    private string $apiKey;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? 'http://localhost:7700';
        // Support both 'key' and 'api_key' config keys
        $this->apiKey = $config['key'] ?? $config['api_key'] ?? '';
    }

    public function indexObject(StorageObject $object): bool
    {
        $document = $this->objectToDocument($object);
        
        try {
            $response = $this->makeRequest(
                'POST',
                "/indexes/" . self::INDEX_NAME . "/documents",
                [$document]
            );
            
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function removeObject(string $bucket, string $key): bool
    {
        $documentId = $this->generateDocumentId($bucket, $key);
        
        try {
            $response = $this->makeRequest(
                'DELETE',
                "/indexes/" . self::INDEX_NAME . "/documents/{$documentId}"
            );
            
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function search(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $params = [
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ];

        // Build filter string
        if (!empty($filters)) {
            $filterStrings = [];
            foreach ($filters as $key => $value) {
                $filterStrings[] = "{$key} = " . json_encode($value);
            }
            $params['filter'] = implode(' AND ', $filterStrings);
        }

        try {
            $response = $this->makeRequest(
                'POST',
                "/indexes/" . self::INDEX_NAME . "/search",
                $params
            );
            
            if ($response === false) {
                return [];
            }

            $data = json_decode($response, true);
            return $data['hits'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function updateObjectMetadata(string $bucket, string $key, array $metadata): bool
    {
        $documentId = $this->generateDocumentId($bucket, $key);
        
        $update = [
            'id' => $documentId,
            'metadata' => $metadata,
        ];

        try {
            $response = $this->makeRequest(
                'PUT',
                "/indexes/" . self::INDEX_NAME . "/documents",
                [$update]
            );
            
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clearIndex(): bool
    {
        try {
            $response = $this->makeRequest(
                'DELETE',
                "/indexes/" . self::INDEX_NAME . "/documents"
            );
            
            return $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function objectToDocument(StorageObject $object): array
    {
        return [
            'id' => $this->generateDocumentId($object->bucket, $object->key),
            'bucket' => $object->bucket,
            'key' => $object->key,
            'size' => $object->size,
            'content_type' => $object->contentType,
            'metadata' => $object->metadata,
            'version_id' => $object->versionId,
        ];
    }

    private function generateDocumentId(string $bucket, string $key): string
    {
        return base64_encode("{$bucket}:{$key}");
    }

    private function makeRequest(string $method, string $path, $body = null): string|false
    {
        $url = rtrim($this->host, '/') . $path;
        
        $headers = [
            'Content-Type: application/json',
        ];
        
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $options['http']['content'] = json_encode($body);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        // Check HTTP response code
        if ($response === false) {
            return false;
        }
        
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
                $statusCode = (int) $matches[1];
                if ($statusCode >= 400) {
                    return false;
                }
            }
        }
        
        return $response;
    }
}
