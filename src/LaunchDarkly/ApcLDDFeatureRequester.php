<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\Integrations\ApcFeatureRequesterCache;
use LaunchDarkly\Impl\Integrations\RedisFeatureRequester;

/**
 * Deprecated feature requester from an LDD-populated Redis, with APC caching.
 *
 * @deprecated Per the docs (http://php.net/manual/en/intro.apc.php):
 * "This extension (APC) is considered unmaintained and dead".
 *
 * Install APCu and use {@link \LaunchDarkly\Integrations\Redis::newFeatureRequester()} instead!
 *
 * @package LaunchDarkly
 */
class ApcLDDFeatureRequester extends RedisFeatureRequester
{
    protected function createCache($options)
    {
        $expiration = isset($options['apc_expiration']) ? (int)$options['apc_expiration'] : 30;
        return new ApcFeatureRequesterCache($expiration);
    }
}
