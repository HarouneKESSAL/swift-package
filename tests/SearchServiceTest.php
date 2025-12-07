<?php

declare(strict_types=1);

use Swift\Config\SwiftConfig;
use Swift\Model\StorageObject;
use Swift\Search\SearchService;
use Orchestra\Testbench\TestCase;

final class SearchServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Swift\SwiftServiceProvider::class];
    }

    public function test_search_disabled_by_default(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $this->assertFalse($service->isEnabled());
    }

    public function test_search_enabled(): void
    {
        $cfg = new SwiftConfig([
            'search' => [
                'enabled' => true,
                'provider' => 'meilisearch',
                'meilisearch' => [
                    'host' => 'http://localhost:7700',
                    'api_key' => '',
                ],
            ],
        ]);
        $service = new SearchService($cfg);
        
        $this->assertTrue($service->isEnabled());
    }

    public function test_unsupported_provider_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported search provider: invalid');
        
        $cfg = new SwiftConfig([
            'search' => [
                'enabled' => true,
                'provider' => 'invalid',
            ],
        ]);
        new SearchService($cfg);
    }

    public function test_index_object_when_disabled_returns_false(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $object = new StorageObject(
            bucket: 'test-bucket',
            key: 'test-key',
            contentType: 'text/plain',
            size: 100
        );
        
        $result = $service->indexObject($object);
        $this->assertFalse($result);
    }

    public function test_remove_object_when_disabled_returns_false(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $result = $service->removeObject('test-bucket', 'test-key');
        $this->assertFalse($result);
    }

    public function test_search_when_disabled_returns_empty_array(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $results = $service->search('test query');
        $this->assertSame([], $results);
    }

    public function test_update_object_metadata_when_disabled_returns_false(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $result = $service->updateObjectMetadata('test-bucket', 'test-key', ['key' => 'value']);
        $this->assertFalse($result);
    }

    public function test_clear_index_when_disabled_returns_false(): void
    {
        $cfg = new SwiftConfig([
            'search' => ['enabled' => false],
        ]);
        $service = new SearchService($cfg);
        
        $result = $service->clearIndex();
        $this->assertFalse($result);
    }
}
