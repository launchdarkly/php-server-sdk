<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\Integrations\RedisFeatureRequester;

/**
 * Deprecated implementation class for Redis integration.
 * Replaced by {@link \LaunchDarkly\Integrations\Redis::featureRequester()}.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Redis::featureRequester()}
 */
class LDDFeatureRequester extends RedisFeatureRequester
{
    protected function createCache($options)
    {
        // The new base class has optional caching behavior, but this deprecated class doesn't.
        return null;
    }
}
