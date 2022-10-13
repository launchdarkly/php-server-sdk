<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

/**
 * @ignore
 * @internal
 */
interface FeatureRequesterCache
{
    /**
     * Read a value from the cache.
     *
     * @param string $cacheKey the unique key
     * @return string the cached value, or null if not found
     */
    public function getCachedString(string $cacheKey): ?string;

    /**
     * Store a value in the cache.
     *
     * @param string $cacheKey the unique key
     * @param string $data the string value
     */
    public function putCachedString(string $cacheKey, ?string $data): void;
}
