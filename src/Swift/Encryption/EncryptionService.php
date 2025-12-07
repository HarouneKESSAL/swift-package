<?php

declare(strict_types=1);

namespace Swift\Encryption;

use Swift\Config\SwiftConfig;

final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32; // 256 bits
    private const TAG_LENGTH = 16;

    private string $masterKey;
    private bool $enabled;

    public function __construct(SwiftConfig $config)
    {
        $encryptionConfig = $this->getEncryptionConfig($config);
        $this->enabled = (bool) ($encryptionConfig['enabled'] ?? false);
        
        if ($this->enabled) {
            $masterKey = $encryptionConfig['master_key'] ?? '';
            if (empty($masterKey)) {
                throw new \RuntimeException('Encryption is enabled but master_key is not set');
            }
            $this->masterKey = $this->deriveMasterKey($masterKey);
        } else {
            $this->masterKey = '';
        }
    }

    private function getEncryptionConfig(SwiftConfig $config): array
    {
        $ref = new \ReflectionClass($config);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $data = $prop->getValue($config);
        return (array) ($data['encryption'] ?? []);
    }

    private function deriveMasterKey(string $key): string
    {
        // Derive a 256-bit key from the provided master key
        return hash('sha256', $key, true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Encrypt data using envelope encryption.
     * 
     * @param string $plaintext The data to encrypt
     * @return array Returns ['ciphertext' => base64, 'data_key' => base64, 'iv' => base64, 'tag' => base64]
     */
    public function encrypt(string $plaintext): array
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Encryption is not enabled');
        }

        // Generate a random data encryption key (DEK)
        $dataKey = random_bytes(self::KEY_LENGTH);
        
        // Generate IV for data encryption
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        
        // Encrypt the plaintext with the data key
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $dataKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Encrypt the data key with the master key (envelope encryption)
        $encryptedDataKey = $this->encryptDataKey($dataKey);

        return [
            'ciphertext' => base64_encode($ciphertext),
            'data_key' => base64_encode($encryptedDataKey['encrypted']),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'key_iv' => base64_encode($encryptedDataKey['iv']),
            'key_tag' => base64_encode($encryptedDataKey['tag']),
        ];
    }

    /**
     * Decrypt data using envelope encryption.
     * 
     * @param array $encryptedData Array with keys: ciphertext, data_key, iv, tag, key_iv, key_tag
     * @return string The decrypted plaintext
     */
    public function decrypt(array $encryptedData): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Encryption is not enabled');
        }

        $requiredKeys = ['ciphertext', 'data_key', 'iv', 'tag', 'key_iv', 'key_tag'];
        foreach ($requiredKeys as $key) {
            if (!isset($encryptedData[$key])) {
                throw new \InvalidArgumentException("Missing required key: {$key}");
            }
        }

        // Decrypt the data key using the master key
        $dataKey = $this->decryptDataKey([
            'encrypted' => base64_decode($encryptedData['data_key']),
            'iv' => base64_decode($encryptedData['key_iv']),
            'tag' => base64_decode($encryptedData['key_tag']),
        ]);

        // Decrypt the ciphertext using the data key
        $plaintext = openssl_decrypt(
            base64_decode($encryptedData['ciphertext']),
            self::CIPHER,
            $dataKey,
            OPENSSL_RAW_DATA,
            base64_decode($encryptedData['iv']),
            base64_decode($encryptedData['tag'])
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }

    private function encryptDataKey(string $dataKey): array
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $dataKey,
            self::CIPHER,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Data key encryption failed');
        }

        return [
            'encrypted' => $encrypted,
            'iv' => $iv,
            'tag' => $tag,
        ];
    }

    private function decryptDataKey(array $encryptedKey): string
    {
        $dataKey = openssl_decrypt(
            $encryptedKey['encrypted'],
            self::CIPHER,
            $this->masterKey,
            OPENSSL_RAW_DATA,
            $encryptedKey['iv'],
            $encryptedKey['tag']
        );

        if ($dataKey === false) {
            throw new \RuntimeException('Data key decryption failed');
        }

        return $dataKey;
    }

    /**
     * Rotate encryption by re-encrypting data with a new data key.
     * 
     * @param array $oldEncryptedData The existing encrypted data
     * @return array New encrypted data with a new data key
     */
    public function rotateKey(array $oldEncryptedData): array
    {
        // Decrypt with old keys
        $plaintext = $this->decrypt($oldEncryptedData);
        
        // Re-encrypt with new data key
        return $this->encrypt($plaintext);
    }
}
