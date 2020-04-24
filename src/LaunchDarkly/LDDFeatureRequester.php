<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\Integrations\RedisFeatureRequester;

/**
 * Deprecated integration class that reads feature flags from Redis.
 *
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
