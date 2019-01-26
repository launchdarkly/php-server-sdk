<?php
namespace LaunchDarkly\Impl\Integrations;

/**
 * Caching implementation based on the APCu extension. This is used by default by all database feature
 * requester implementations if the 'apc_expiration' property is set.
 */
class ApcuFeatureRequesterCache implements FeatureRequesterCache
{
    /** @var int */
    private $_expiration;

    public function __construct($expiration)
    {
        if (!extension_loaded('apcu')) {
            throw new \InvalidArgumentException('apc_expiration was specified but apcu is not installed');
        }
        $this->_expiration = $expiration;
    }

    public function getCachedString($cacheKey)
    {
        $value = \apcu_fetch($cacheKey);
        return $value === false ? null : $value;
    }

    public function putCachedString($cacheKey, $data)
    {
        \apcu_store($cacheKey, $data, $this->_expiration);
    }
}
