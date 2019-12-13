<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Integrations\PHPRedis;

/**
 * @requires extension redis
 */
class PHPRedisFeatureRequesterTest extends FeatureRequesterTestBase
{
    const PREFIX = 'test';

    /** @var \Redis */
    private static $redisClient;
    
    public static function setUpBeforeClass()
    {
        if (!static::isSkipDatabaseTests()) {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', 6379);
            self::$redisClient = $redis;
        }
    }

    protected function isDatabaseTest()
    {
        return true;
    }
    
    protected function makeRequester()
    {
        $factory = PHPRedis::featureRequester();
        return $factory('', '', array('redis_prefix' => self::PREFIX));
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$redisClient->hset(self::PREFIX . ":$namespace", $key, $json);
    }

    protected function deleteExistingData()
    {
        self::$redisClient->flushdb();
    }
}
