<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Integrations\PHPRedis;

/**
 * @requires extension redis
 */
class PHPRedisFeatureRequesterWithClientTest extends FeatureRequesterTestBase
{
    const CLIENT_PREFIX = 'clientprefix';
    const LD_PREFIX = 'ldprefix';

    /** @var \Redis */
    private static $redisClient;
    
    public static function setUpBeforeClass()
    {
        if (!static::isSkipDatabaseTests()) {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->setOption(\Redis::OPT_PREFIX, self::CLIENT_PREFIX);
            self::$redisClient = $redis;

            // Setting a prefix parameter on the Redis client causes it to prepend
            // that string to every key *in addition to* the other prefix that the SDK
            // integration is applying (LD_PREFIX). This is done transparently so we
            // do not need to add CLIENT_PREFIX in putItem below. We're doing it so we
            // can be sure that the PHPRedisFeatureRequester really is using the same
            // client we passed to it; if it didn't, the tests would fail because
            // putItem was creating keys with both prefixes but PHPRedisFeatureRequester
            // was looking for keys with only one prefix.
        }
    }

    protected function isDatabaseTest()
    {
        return true;
    }
    
    protected function makeRequester()
    {
        $factory = PHPRedis::featureRequester();
        return $factory('', '', array(
            'redis_prefix' => self::LD_PREFIX,
            'phpredis_client' => self::$redisClient
        ));
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$redisClient->hset(self::LD_PREFIX . ":$namespace", $key, $json);
    }

    protected function deleteExistingData()
    {
        self::$redisClient->flushdb();
    }
}
