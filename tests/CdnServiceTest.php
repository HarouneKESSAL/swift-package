<?php

declare(strict_types=1);

use Swift\Config\SwiftConfig;
use Swift\CDN\CdnService;
use Orchestra\Testbench\TestCase;

final class CdnServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Swift\SwiftServiceProvider::class];
    }

    public function test_cdn_disabled_by_default(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => ['enabled' => false],
        ]);
        $service = new CdnService($cfg);
        
        $this->assertFalse($service->isEnabled());
    }

    public function test_cdn_enabled_without_secret_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CDN is enabled but secret is not set');
        
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => '',
                'base_url' => 'https://cdn.example.com',
            ],
        ]);
        new CdnService($cfg);
    }

    public function test_cdn_enabled_without_base_url_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CDN is enabled but base_url is not set');
        
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret',
                'base_url' => '',
            ],
        ]);
        new CdnService($cfg);
    }

    public function test_cdn_enabled_with_required_config(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret',
                'base_url' => 'https://cdn.example.com',
            ],
        ]);
        $service = new CdnService($cfg);
        
        $this->assertTrue($service->isEnabled());
    }

    public function test_generate_signed_url(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
                'default_ttl_seconds' => 3600,
            ],
        ]);
        $service = new CdnService($cfg);
        
        $url = $service->generateSignedUrl('bucket/object.txt', 3600);
        
        $this->assertStringContainsString('https://cdn.example.com/bucket/object.txt', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_verify_signed_url(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
                'default_ttl_seconds' => 3600,
            ],
        ]);
        $service = new CdnService($cfg);
        
        // Generate a URL
        $url = $service->generateSignedUrl('bucket/object.txt', 3600);
        
        // Verify it
        $this->assertTrue($service->verifySignedUrl($url));
    }

    public function test_verify_expired_url(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
            ],
        ]);
        $service = new CdnService($cfg);
        
        // Generate a URL that expires in 1 second
        $url = $service->generateSignedUrl('bucket/object.txt', 1);
        
        // Wait for it to expire
        sleep(2);
        
        // Verify it fails
        $this->assertFalse($service->verifySignedUrl($url));
    }

    public function test_verify_invalid_signature(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
            ],
        ]);
        $service = new CdnService($cfg);
        
        // Generate a valid URL
        $url = $service->generateSignedUrl('bucket/object.txt', 3600);
        
        // Tamper with the signature
        $url = str_replace('signature=', 'signature=invalid', $url);
        
        // Verify it fails
        $this->assertFalse($service->verifySignedUrl($url));
    }

    public function test_generate_signed_url_with_ip_binding(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
                'bind_ip' => true,
            ],
        ]);
        $service = new CdnService($cfg);
        
        $clientIp = '192.168.1.1';
        $url = $service->generateSignedUrl('bucket/object.txt', 3600, $clientIp);
        
        $this->assertStringContainsString('ip=192.168.1.1', $url);
        
        // Verify with correct IP
        $this->assertTrue($service->verifySignedUrl($url, $clientIp));
        
        // Verify with wrong IP fails
        $this->assertFalse($service->verifySignedUrl($url, '192.168.1.2'));
    }

    public function test_invalidate(): void
    {
        $cfg = new SwiftConfig([
            'cdn' => [
                'enabled' => true,
                'secret' => 'test-secret-key',
                'base_url' => 'https://cdn.example.com',
            ],
        ]);
        $service = new CdnService($cfg);
        
        // This is a placeholder test since invalidate is a stub
        $result = $service->invalidate('bucket/object.txt');
        $this->assertTrue($result);
    }
}
