<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDClient;
use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\LDDFeatureRequester;
use LaunchDarkly\Integrations\Redis;
use LaunchDarkly\ApcLDDFeatureRequester;
use Predis\Client;
use LaunchDarkly\ApcuLDDFeatureRequester;

/**
 * These tests use the LaunchDarkly Redis integration along with the optional caching behavior.
 * They are meant to be run in a PHP environment that has APCu installed. They will also test the
 * deprecated APC caching mode.
 */
class CachedRedisFeatureRequesterTest extends \PHPUnit_Framework_TestCase
{
    const CACHE_SECONDS = 60;

    public function testGetUncached()
    {
        $redis = self::makeRedisClient();

        $client = self::makeLDClient([
            'feature_requester' => Redis::featureRequester()
        ]);
        $user = self::makeUser();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->variation('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', self::genFeature("foo", "bar"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));
    }

    public function testGetApc()
    {
        if (!extension_loaded('apc')) {
            self::markTestSkipped('Install `apc` extension to run this test.');
        }

        $this->doCachedGetTest(
            [
                // Redis::featureRequester() will always use APCu for caching, rather than APC. The only
                // way to specify APC is to use the deprecated ApcLDDFeatureRequester class.
                'feature_requester_class' => ApcLDDFeatureRequester::class,
                'apc_expiration' => static::CACHE_SECONDS
            ],
            function ($cacheKey) {
                apc_delete($cacheKey);
            });
    }

    public function testGetApcu()
    {
        if (!extension_loaded('apcu')) {
            self::markTestSkipped('Install `apcu` extension to run this test.');
        }

        $this->doCachedGetTest(
            [
                'feature_requester' => Redis::featureRequester([ 'apc_expiration' => static::CACHE_SECONDS ])
            ],
            function ($cacheKey) {
                apcu_delete($cacheKey);
            });
    }

    private function doCachedGetTest($options, $clearFn)
    {
        $featureKey = 'fiz';
        $firstValue = 'first';
        $secondValue = 'second';
        $defaultValue = 'default';

        $redis = self::makeRedisClient();

        $client = self::makeLDClient($options);
        $user = self::makeUser();

        $redis->del('launchdarkly:features');
        $clearFn("launchdarkly:features:{$featureKey}");

        $this->assertEquals($defaultValue, $client->variation($featureKey, $user, $defaultValue));
        $redis->hset('launchdarkly:features', $featureKey, self::genFeature($featureKey, $firstValue));
        $this->assertEquals($firstValue, $client->variation($featureKey, $user, $defaultValue));

        # cached value so not updated
        $redis->hset('launchdarkly:features', $featureKey, self::genFeature($featureKey, $secondValue));
        $this->assertEquals($firstValue, $client->variation($featureKey, $user, $defaultValue));

        $clearFn("launchdarkly:features:{$featureKey}");

        # cache has been cleared, should get new value from Redis
        $this->assertEquals($secondValue, $client->variation($featureKey, $user, $defaultValue));
    }

    public function testGetAllWithoutFeatures()
    {
        $redis = self::makeRedisClient();
        $redis->flushall();

        $client = self::makeLDClient([ 'feature_requester_class' => LDDFeatureRequester::class ]);
        $user = self::makeUser();
        $allFlags = $client->allFlags($user);

        $this->assertEquals(array(), $allFlags);
    }

    public function testGetAllUncached()
    {
        $featureKey = 'foo';
        $featureValue = 'bar';

        $redis = self::makeRedisClient();

        $client = self::makeLDClient([ 'feature_requester' => Redis::featureRequester() ]);
        $user = self::makeUser();

        $redis->hset('launchdarkly:features', $featureKey, self::genFeature($featureKey, $featureValue));
        
        $allFlags = $client->allFlags($user);

        $this->assertInternalType('array', $allFlags);
        $this->assertArrayHasKey($featureKey, $allFlags);
        $this->assertEquals($featureValue, $allFlags[$featureKey]);
    }

    public function testGetAllApc()
    {
        if (!extension_loaded('apc')) {
            self::markTestSkipped('Install `apc` extension to run this test.');
        }

        $this->doCachedGetAllTest(
            [
                'feature_requester_class' => ApcLDDFeatureRequester::class,
                'apc_expiration' => static::CACHE_SECONDS
            ],
            function ($cacheKey) {
                apc_delete($cacheKey);
            });
    }

    public function testGetAllApcu()
    {
        if (!extension_loaded('apcu')) {
            self::markTestSkipped('Install `apcu` extension to run this test.');
        }

        $this->doCachedGetAllTest(
            [
                'feature_requester' => Redis::featureRequester([ 'apc_expiration' => static::CACHE_SECONDS ])
            ],
            function ($cacheKey) {
                apcu_delete($cacheKey);
            });
    }

    private function doCachedGetAllTest($options, $clearFn)
    {
        $featureKey = 'foo';
        $firstValue = 'first';
        $secondValue  = 'second';

        $redis = self::makeRedisClient();

        $client = self::makeLDClient($options);
        $user = self::makeUser();

        $redis->hset('launchdarkly:features', $featureKey, self::genFeature($featureKey, $firstValue));
        $clearFn('launchdarkly:features:$all');

        $allFlags = $client->allFlags($user);
        $this->assertInternalType('array', $allFlags);
        $this->assertArrayHasKey($featureKey, $allFlags);
        $this->assertEquals($firstValue, $allFlags[$featureKey]);

        $redis->hset('launchdarkly:features', $featureKey, self::genFeature($featureKey, $secondValue));
        
        # should still return cached value
        $allFlags = $client->allFlags($user);
        $this->assertArrayHasKey($featureKey, $allFlags);
        $this->assertEquals($firstValue, $allFlags[$featureKey]);

        $clearFn('launchdarkly:features:$all');

        # cache has been cleared, should get new value from Redis
        $allFlags = $client->allFlags($user);
        $this->assertArrayHasKey($featureKey, $allFlags);
        $this->assertEquals($secondValue, $allFlags[$featureKey]);
    }

    private static function makeLDClient($options)
    {
        $options['send_events'] = false;
        return new LDClient('BOGUS_API_KEY', $options);
    }

    private static function makeRedisClient()
    {
        return new Client(array('scheme' => 'tcp', 'host' => 'localhost', 'port' => 6379));
    }

    private static function makeUser()
    {
        $builder = new LDUserBuilder('userkey');
        return $builder->build();
    }

    private static function genFeature($key, $val)
    {
        $data = [
            'name' => 'Feature ' . $key,
            'key' => $key,
            'kind' => 'flag',
            'salt' => 'Zm9v',
            'on' => true,
            'variations' => [
                $val,
                false,
            ],
            'version' => 4,
            'prerequisites' => [],
            'targets' => [
                [
                    'values' => [
                        $val,
                    ],
                    'variation' => 0,
                ],
                [
                    'values' => [
                        false,
                    ],
                    'variation' => 1,
                ],
            ],
            'rules' => [],
            'fallthrough' => [
                'rollout' => [
                    'variations' => [
                        [
                            'variation' => 0,
                            'weight' => 95000,
                        ],
                        [
                            'variation' => 1,
                            'weight' => 5000,
                        ],
                    ],
                ],
            ],
            'offVariation' => null,
            'deleted' => false,
        ];

        return \json_encode($data);
    }
}
