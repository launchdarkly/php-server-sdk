<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Integrations\Redis;
use Predis\Client;

class RedisFeatureRequesterWithClientTest extends FeatureRequesterTestBase
{
    const CLIENT_PREFIX = 'clientprefix';
    const LD_PREFIX = 'ldprefix';

    /** @var ClientInterface */
    private static $predisClient;
    
    public static function setUpBeforeClass()
    {
        if (!static::isSkipDatabaseTests()) {
            self::$predisClient = new Client(array(), array(
                'prefix' => self::CLIENT_PREFIX
            ));
            // Setting a prefix parameter on the Predis\Client causes it to prepend
            // that string to every key *in addition to* the other prefix that the SDK
            // integration is applying (LD_PREFIX). This is done transparently so we
            // do not need to add CLIENT_PREFIX in putItem below. We're doing it so we
            // can be sure that the RedisFeatureRequester really is using the same
            // client we passed to it; if it didn't, the tests would fail because
            // putItem was creating keys with both prefixes but RedisFeatureRequester
            // was looking for keys with only one prefix.
        }
    }

    protected function isDatabaseTest()
    {
        return true;
    }
    
    protected function makeRequester()
    {
        $factory = Redis::featureRequester();
        return $factory('', '', array(
            'redis_prefix' => self::LD_PREFIX,
            'predis_client' => self::$predisClient
        ));
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$predisClient->hset(self::LD_PREFIX . ":$namespace", $key, $json);
    }

    protected function deleteExistingData()
    {
        self::$predisClient->flushdb();
    }
}
