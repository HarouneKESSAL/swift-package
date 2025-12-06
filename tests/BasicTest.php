<?php

declare(strict_types=1);

use Swift\Config\SwiftConfig;
use Swift\Storage\StorageManager;
use Orchestra\Testbench\TestCase;

final class BasicTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Swift\SwiftServiceProvider::class];
    }

    public function test_local_provider_boots(): void
    {
        $cfg = new SwiftConfig(['storage' => ['provider' => 'local']]);
        $mgr = new StorageManager($cfg);
        $provider = $mgr->provider();
        $this->assertNotNull($provider);
    }
}