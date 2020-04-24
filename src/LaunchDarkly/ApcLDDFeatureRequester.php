<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\Integrations\ApcFeatureRequesterCache;
use LaunchDarkly\Impl\Integrations\RedisFeatureRequester;

/**
 * Deprecated integration class for reading flags from a Redis database, with APC caching.
 *
 * The APC extension (http://php.net/manual/en/intro.apc.php) is no longer maintained and has been
 * replaced by APCu. The new Redis integration which uses APCu caching is
 * {@link \LaunchDarkly\Integrations\Redis::featureRequester()}.
 *
 * @deprecated use Redis::featureRequester()
 */
class ApcLDDFeatureRequester extends RedisFeatureRequester
{
    protected function createCache($options)
    {
        $expiration = isset($options['apc_expiration']) ? (int)$options['apc_expiration'] : 30;
        return new ApcFeatureRequesterCache($expiration);
    }
}
