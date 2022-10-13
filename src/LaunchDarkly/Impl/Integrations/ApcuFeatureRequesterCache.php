<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

/**
 * Caching implementation based on the APCu extension. This is used by default by all database feature
 * requester implementations if the 'apc_expiration' property is set.
 *
 * @ignore
 * @internal
 */
class ApcuFeatureRequesterCache implements FeatureRequesterCache
{
    private int $_expiration;

    public function __construct(int $expiration)
    {
        if (!extension_loaded('apcu')) {
            throw new \InvalidArgumentException('apc_expiration was specified but apcu is not installed');
        }
        $this->_expiration = $expiration;
    }

    public function getCachedString(string $cacheKey): ?string
    {
        $value = \apcu_fetch($cacheKey);
        return $value === false ? null : $value;
    }

    public function putCachedString(string $cacheKey, ?string $data): void
    {
        \apcu_store($cacheKey, $data, $this->_expiration);
    }
}
