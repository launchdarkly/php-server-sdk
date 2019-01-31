<?php

namespace LaunchDarkly;

use LaunchDarkly\Impl\Integrations\ApcuFeatureRequesterCache;
use LaunchDarkly\Impl\Integrations\RedisFeatureRequester;

/**
 * Feature requester from an LDD-populated redis, with APCu caching.
 *
 * Unlike APC, APCu is actively maintained and is available from php53 to php7.
 *
 * This class is deprecated. Use {@link \LaunchDarkly\Integrations\Redis::featureRequester()}
 * and set the `apc_expiration` option.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Redis::featureRequester()} and set the `apc_expiration` option.
 *
 * @package LaunchDarkly
 */
class ApcuLDDFeatureRequester extends RedisFeatureRequester
{
    protected function createCache($options)
    {
        $expiration = isset($options['apc_expiration']) ? (int)$options['apc_expiration'] : 30;
        return new ApcuFeatureRequesterCache($expiration);
    }
}
