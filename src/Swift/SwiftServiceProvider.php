<?php

declare(strict_types=1);

namespace Swift;

use Swift\Config\SwiftConfig;
use Swift\Service\BucketService;
use Swift\Service\StorageService;
use Swift\Storage\StorageManager;
use Swift\Encryption\EncryptionService;
use Swift\CDN\CdnService;
use Swift\Search\SearchService;
use Illuminate\Support\ServiceProvider;

final class SwiftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/swift.php', 'swift');

        $this->app->singleton(SwiftConfig::class, fn () => new SwiftConfig(config('swift', [])));

        $this->app->singleton(StorageManager::class, function ($app) {
            return new StorageManager($app->make(SwiftConfig::class));
        });

        $this->app->singleton(StorageService::class, function ($app) {
            return new StorageService(
                $app->make(StorageManager::class),
                $app->make(SwiftConfig::class),
                $app->make(SearchService::class)
            );
        });

        $this->app->singleton(BucketService::class, function ($app) {
            return new BucketService(
                $app->make(StorageManager::class),
                $app->make(SwiftConfig::class)
            );
        });

        $this->app->singleton(EncryptionService::class, function ($app) {
            return new EncryptionService($app->make(SwiftConfig::class));
        });

        $this->app->singleton(CdnService::class, function ($app) {
            return new CdnService($app->make(SwiftConfig::class));
        });

        $this->app->singleton(SearchService::class, function ($app) {
            return new SearchService($app->make(SwiftConfig::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/swift.php' => config_path('swift.php'),
        ], 'swift-config');
    }
}