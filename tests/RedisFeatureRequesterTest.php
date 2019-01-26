<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Integrations\Redis;
use Predis\Client;

class RedisFeatureRequesterTest extends FeatureRequesterTestBase
{
    const PREFIX = 'test';

    /** @var ClientInterface */
    private static $predisClient;
    
    public static function setUpBeforeClass()
    {
        self::$predisClient = new Client(array());
    }
    
    protected function makeRequester()
    {
        $factory = Redis::featureRequester();
        return $factory('', '', array('redis_prefix' => self::PREFIX));
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$predisClient->hset(self::PREFIX . ":$namespace", $key, $json);
    }

    protected function deleteExistingData()
    {
    }
}
