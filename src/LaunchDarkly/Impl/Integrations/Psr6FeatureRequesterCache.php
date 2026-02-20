<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @ignore
 * @internal
 */
class Psr6FeatureRequesterCache implements FeatureRequesterCache
{
    public function __construct(
        private CacheItemPoolInterface $pool,
        private ?int $ttl = null
    ) {
    }

    public function getCachedString(string $cacheKey): ?string
    {
        $item = $this->pool->getItem($this->sanitizeKey($cacheKey));
        return $item->isHit() ? $item->get() : null;
    }

    public function putCachedString(string $cacheKey, ?string $data): void
    {
        $item = $this->pool->getItem($this->sanitizeKey($cacheKey));
        $item->set($data);
        $item->expiresAfter($this->ttl);
        $this->pool->save($item);
    }

    /**
     * PSR-6 only mandates support for A-Za-z0-9._ in cache keys.
     * Current keys use ":" and "$" (e.g. "launchdarkly:features:$all").
     * Hex-encode unsafe chars to avoid collisions.
     */
    private function sanitizeKey(string $key): string
    {
        return (string) preg_replace_callback('/[^A-Za-z0-9.]/', function ($m) {
            return '_' . bin2hex($m[0]);
        }, $key);
    }
}
