<?php

namespace LaunchDarkly\Impl\Integrations;

/**
 * Deprecated caching implementation based on the APC extension.
 *
 * @ignore
 * @internal
 */
class ApcFeatureRequesterCache implements FeatureRequesterCache
{
    /** @var int */
    private $_expiration;

    public function __construct(int $expiration)
    {
        if (!extension_loaded('apcu')) {
            throw new \InvalidArgumentException('apc_expiration was specified but apcu is not installed');
        }
        $this->_expiration = $expiration;
    }

    public function getCachedString(string $cacheKey): ?string
    {
        $value = \apc_fetch($cacheKey);
        return $value === false ? null : $value;
    }

    public function putCachedString(string $cacheKey, ?string $data): void
    {
        \apc_store($cacheKey, $data, $this->_expiration);
    }
}
