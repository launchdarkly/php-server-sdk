<?php

namespace LaunchDarkly;

/**
 * Feature requester from an LDD-populated redis, with APCu caching.
 *
 * Unlike APC, APCu is actively maintained and is available from php53 to php7.
 *
 * This class is deprecated. Use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()}
 * and set the `apc_expiration` option.
 *
 * @deprecated Use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()} and set the `apc_expiration` option.
 *
 * @package LaunchDarkly
 */
class ApcuLDDFeatureRequester extends ApcLDDFeatureRequester
{
    /**
     * @param $key
     * @param null $success
     * @return mixed
     */
    protected function fetch($key, &$success = null)
    {
        return \apcu_fetch($key, $success);
    }

    /**
     * @param $key
     * @param $var
     * @param int $ttl
     * @return bool
     */
    protected function add($key, $var, $ttl = 0)
    {
        return \apcu_add($key, $var, $ttl);
    }
}
