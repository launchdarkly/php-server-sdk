<?php
namespace LaunchDarkly\Impl\Integrations;

/**
 * Deprecated caching implementation based on the APC extension.
 *
 * @deprecated Per the docs (http://php.net/manual/en/intro.apc.php):
 * "This extension (APC) is considered unmaintained and dead".
 */
class ApcFeatureRequesterCache implements FeatureRequesterCache
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
        $value = \apc_fetch($cacheKey);
        return $value === false ? null : $value;
    }

    public function putCachedString($cacheKey, $data)
    {
        \apc_store($cacheKey, $data, $this->_expiration);
    }
}
