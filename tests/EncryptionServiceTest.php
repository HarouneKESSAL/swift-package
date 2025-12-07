<?php

declare(strict_types=1);

use Swift\Config\SwiftConfig;
use Swift\Encryption\EncryptionService;
use Orchestra\Testbench\TestCase;

final class EncryptionServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\Swift\SwiftServiceProvider::class];
    }

    public function test_encryption_disabled_by_default(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => ['enabled' => false],
        ]);
        $service = new EncryptionService($cfg);
        
        $this->assertFalse($service->isEnabled());
    }

    public function test_encryption_enabled_without_key_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption is enabled but master_key is not set');
        
        $cfg = new SwiftConfig([
            'encryption' => ['enabled' => true, 'master_key' => ''],
        ]);
        new EncryptionService($cfg);
    }

    public function test_encryption_enabled_with_key(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => [
                'enabled' => true,
                'master_key' => 'test-master-key-12345',
            ],
        ]);
        $service = new EncryptionService($cfg);
        
        $this->assertTrue($service->isEnabled());
    }

    public function test_encrypt_and_decrypt(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => [
                'enabled' => true,
                'master_key' => 'test-master-key-12345',
            ],
        ]);
        $service = new EncryptionService($cfg);
        
        $plaintext = 'Hello, World! This is a test message.';
        $encrypted = $service->encrypt($plaintext);
        
        $this->assertIsArray($encrypted);
        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('data_key', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);
        $this->assertArrayHasKey('key_iv', $encrypted);
        $this->assertArrayHasKey('key_tag', $encrypted);
        
        $decrypted = $service->decrypt($encrypted);
        $this->assertSame($plaintext, $decrypted);
    }

    public function test_encrypt_when_disabled_throws_exception(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => ['enabled' => false],
        ]);
        $service = new EncryptionService($cfg);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption is not enabled');
        
        $service->encrypt('test');
    }

    public function test_decrypt_when_disabled_throws_exception(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => ['enabled' => false],
        ]);
        $service = new EncryptionService($cfg);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption is not enabled');
        
        $service->decrypt([]);
    }

    public function test_key_rotation(): void
    {
        $cfg = new SwiftConfig([
            'encryption' => [
                'enabled' => true,
                'master_key' => 'test-master-key-12345',
            ],
        ]);
        $service = new EncryptionService($cfg);
        
        $plaintext = 'Test data for key rotation';
        $encrypted1 = $service->encrypt($plaintext);
        
        // Rotate the key (re-encrypt with new data key)
        $encrypted2 = $service->rotateKey($encrypted1);
        
        $this->assertIsArray($encrypted2);
        $this->assertNotSame($encrypted1['data_key'], $encrypted2['data_key']);
        $this->assertNotSame($encrypted1['ciphertext'], $encrypted2['ciphertext']);
        
        // Both should decrypt to the same plaintext
        $decrypted1 = $service->decrypt($encrypted1);
        $decrypted2 = $service->decrypt($encrypted2);
        
        $this->assertSame($plaintext, $decrypted1);
        $this->assertSame($plaintext, $decrypted2);
    }
}
