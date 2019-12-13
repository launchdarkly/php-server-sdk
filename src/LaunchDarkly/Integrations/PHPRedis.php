<?php

namespace LaunchDarkly\Integrations;

/**
 * Integration with a Redis data store.
 * @since ???
 */
class PHPRedis
{
    /**
     * Configures an adapter for reading feature flag data from Redis using persistent connections.
     *
     * To use this method, you must have installed the `phpredis` extension. After calling this
     * method, store its return value in the `feature_requester` property of your client configuration:
     *
     *     $fr = LaunchDarkly\Integrations\PHPRedis::featureRequester([ "redis_prefix" => "env1" ]);
     *     $config = [ "feature_requester" => $fr ];
     *     $client = new LDClient("sdk_key", $config);
     *
     * For more about using LaunchDarkly with databases, see the
     * [SDK reference guide](https://docs.launchdarkly.com/v2.0/docs/using-a-persistent-feature-store).
     *
     * @param array $options  Configuration settings (can also be passed in the main client configuration):
     *   - `redis_host`: hostname of the Redis server; defaults to `localhost`
     *   - `redis_port`: port of the Redis server; defaults to 6379
     *   - `redis_timeout`: connection timeout in seconds; defaults to 5
     *   - `redis_prefix`: a string to be prepended to all database keys; corresponds to the prefix
     * setting in ld-relay
     *   - `phpredis_client`: an already-configured Predis client instance if you wish to reuse one
     *   - `apc_expiration`: expiration time in seconds for local caching, if `APCu` is installed
     * @return object  an object to be stored in the `feature_requester` configuration property
     */
    public static function featureRequester($options = array())
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException("phpredis extension is required to use Integrations::PHPRedis");
        }
        return function ($baseUri, $sdkKey, $baseOptions) use ($options) {
            return new \LaunchDarkly\Impl\Integrations\PHPRedisFeatureRequester($baseUri, $sdkKey,
                array_merge($baseOptions, $options));
        };
    }
}
