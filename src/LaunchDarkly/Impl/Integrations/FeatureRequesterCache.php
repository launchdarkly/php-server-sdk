<?php
namespace LaunchDarkly\Impl\Integrations;

interface FeatureRequesterCache
{
    /**
     * Read a value from the cache.
     *
     * @param $cacheKey the unique key
     * @return string the cached value, or null if not found
     */
    public function getCachedString(string $cacheKey): ?string;

    /**
     * Store a value in the cache.
     *
     * @param $cacheKey the unique key
     * @param $data the string value
     */
    public function putCachedString(string $cacheKey, ?string $data): void;
}
