<?php

declare(strict_types=1);

namespace Swift\CDN;

use Swift\Config\SwiftConfig;

final class CdnService
{
    private const HASH_ALGO = 'sha256';
    
    private string $secret;
    private bool $enabled;
    private int $defaultTtl;
    private bool $bindIp;
    private string $baseUrl;

    public function __construct(SwiftConfig $config)
    {
        $cdnConfig = $this->getCdnConfig($config);
        $this->enabled = (bool) ($cdnConfig['enabled'] ?? false);
        
        if ($this->enabled) {
            $secret = $cdnConfig['secret'] ?? '';
            if (empty($secret)) {
                throw new \RuntimeException('CDN is enabled but secret is not set');
            }
            $this->secret = $secret;
            $this->baseUrl = $cdnConfig['base_url'] ?? '';
            if (empty($this->baseUrl)) {
                throw new \RuntimeException('CDN is enabled but base_url is not set');
            }
        } else {
            $this->secret = '';
            $this->baseUrl = '';
        }
        
        $this->defaultTtl = (int) ($cdnConfig['default_ttl_seconds'] ?? 3600);
        $this->bindIp = (bool) ($cdnConfig['bind_ip'] ?? false);
    }

    private function getCdnConfig(SwiftConfig $config): array
    {
        $ref = new \ReflectionClass($config);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        $data = $prop->getValue($config);
        return (array) ($data['cdn'] ?? []);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Generate a signed CDN URL.
     * 
     * @param string $path The path to the resource (e.g., "bucket/key")
     * @param int|null $ttlSeconds TTL in seconds, null for default
     * @param string|null $clientIp Client IP address for IP binding (optional)
     * @return string The signed URL
     */
    public function generateSignedUrl(string $path, ?int $ttlSeconds = null, ?string $clientIp = null): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('CDN is not enabled');
        }

        $ttl = $ttlSeconds ?? $this->defaultTtl;
        $expiration = time() + $ttl;
        
        // Build the base URL
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        
        // Generate signature
        $signature = $this->generateSignature($path, $expiration, $clientIp);
        
        // Build query parameters
        $params = [
            'expires' => $expiration,
            'signature' => $signature,
        ];
        
        if ($this->bindIp && $clientIp !== null) {
            $params['ip'] = $clientIp;
        }
        
        return $url . '?' . http_build_query($params);
    }

    /**
     * Verify a signed CDN URL.
     * 
     * @param string $url The full signed URL
     * @param string|null $clientIp The client IP address (required if IP binding is enabled)
     * @return bool True if the signature is valid and not expired
     */
    public function verifySignedUrl(string $url, ?string $clientIp = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Parse the URL
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['query'])) {
            return false;
        }

        parse_str($parts['query'], $params);
        
        // Check required parameters
        if (!isset($params['expires']) || !isset($params['signature'])) {
            return false;
        }

        $expiration = (int) $params['expires'];
        $providedSignature = $params['signature'];
        
        // Check if expired
        if (time() > $expiration) {
            return false;
        }

        // Extract path from URL
        $path = $parts['path'] ?? '';
        $baseUrlParts = parse_url($this->baseUrl);
        $basePath = $baseUrlParts['path'] ?? '';
        if (!empty($basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $path = ltrim($path, '/');

        // Check IP binding if enabled
        $ipForSignature = null;
        if ($this->bindIp) {
            if ($clientIp === null) {
                return false;
            }
            $ipForSignature = $clientIp;
            
            // Verify IP matches if provided in URL
            if (isset($params['ip']) && $params['ip'] !== $clientIp) {
                return false;
            }
        }

        // Generate expected signature
        $expectedSignature = $this->generateSignature($path, $expiration, $ipForSignature);
        
        // Constant-time comparison to prevent timing attacks
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Generate HMAC signature.
     * 
     * @param string $path The resource path
     * @param int $expiration Unix timestamp
     * @param string|null $clientIp Optional client IP
     * @return string The HMAC signature
     */
    private function generateSignature(string $path, int $expiration, ?string $clientIp): string
    {
        $data = $path . '|' . $expiration;
        
        if ($this->bindIp && $clientIp !== null) {
            $data .= '|' . $clientIp;
        }
        
        return hash_hmac(self::HASH_ALGO, $data, $this->secret);
    }

    /**
     * Invalidate a CDN cache for a specific path.
     * Note: This is a placeholder for actual CDN invalidation logic.
     * 
     * @param string $path The path to invalidate
     * @return bool True if invalidation was successful
     */
    public function invalidate(string $path): bool
    {
        // Placeholder for CDN invalidation
        // In a real implementation, this would call the CDN provider's API
        // to invalidate or purge the cache for the given path
        return true;
    }
}
