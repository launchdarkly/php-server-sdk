<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\LDClient;
use LaunchDarkly\LDUser;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\LDDFeatureRequester;
use LaunchDarkly\ApcLDDFeatureRequester;
use Predis\Client;
use LaunchDarkly\ApcuLDDFeatureRequester;

class LDDFeatureRetrieverTest extends \PHPUnit_Framework_TestCase {
    const API_KEY = 'BOGUS_API_KEY';

    public function testGet() {
        $redis = new Client(array(
                                      "scheme" => "tcp",
                                      "host" => 'localhost',
                                      "port" => 6379));
        $client = new LDClient(static::API_KEY, array('feature_requester_class' => LDDFeatureRequester::class));
        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->variation('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));
    }

    public function testGetApc() {
        if (!extension_loaded('apc')) {
            self::markTestSkipped('Install `apc` extension to run this test.');
        }
        $redis = new Client(array(
                                        "scheme" => "tcp",
                                        "host" => 'localhost',
                                        "port" => 6379));
        $client = new LDClient(static::API_KEY, array('feature_requester_class' => ApcLDDFeatureRequester::class,
            'apc_expiration' => 1));
        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->variation('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));

        # cached value so not updated
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "baz"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));

        apc_delete("launchdarkly:features.foo");
        $this->assertEquals("baz", $client->variation('foo', $user, 'jim'));
    }

    public function testGetApcu() {
        if (!extension_loaded('apcu')) {
            self::markTestSkipped('Install `apcu` extension to run this test.');
        }

        $redis = new Client([
            'scheme' => 'tcp',
            'host' => 'localhost',
            'port' => 6379
        ]);

        $client = new LDClient(static::API_KEY, [
            'feature_requester_class' => ApcuLDDFeatureRequester::class,
            'apc_expiration' => 1
        ]);

        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del('launchdarkly:features');
        $this->assertEquals('alice', $client->variation('fiz', $user, 'alice'));
        $redis->hset('launchdarkly:features', 'fiz', $this->gen_feature('fiz', 'buz'));
        $this->assertEquals('buz', $client->variation('fiz', $user, 'alice'));

        # cached value so not updated
        $redis->hset('launchdarkly:features', 'fiz', $this->gen_feature('fiz', 'bob'));
        $this->assertEquals('buz', $client->variation('fiz', $user, 'alice'));

        \apcu_delete('launchdarkly:features.fiz');
        $this->assertEquals('bob', $client->variation('fiz', $user, 'alice'));
    }

    public function testGetAllWithoutFeatures()
    {
        $redis = new \Predis\Client([
            'scheme' => 'tcp',
            'host' => 'localhost',
            'port' => 6379,
        ]);
        $redis->flushall();

        $client = new LDClient(static::API_KEY, ['feature_requester_class' => LDDFeatureRequester::class]);
        $user = new LDUser(static::API_KEY);
        $allFlags = $client->allFlags($user);

        $this->assertNull($allFlags);
    }

    public function testGetAll()
    {
        $featureKey = 'foo';
        $featureValue = 'bar';

        $redis = new \Predis\Client([
            'scheme' => 'tcp',
            'host' => 'localhost',
            'port' => 6379,
        ]);
        $client = new LDClient(static::API_KEY, ['feature_requester_class' => LDDFeatureRequester::class]);
        $redis->hset('launchdarkly:features', $featureKey, $this->gen_feature($featureKey, $featureValue));
        $user = new LDUser(static::API_KEY);
        $allFlags = $client->allFlags($user);

        $this->assertInternalType('array', $allFlags);
        $this->assertArrayHasKey($featureKey, $allFlags);
        $this->assertEquals($featureValue, $allFlags[$featureKey]);
    }

    private function gen_feature($key, $val) {
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
            'commitDate' => '2015-09-08T21:24:16.712Z',
            'creationDate' => '2015-09-08T21:06:16.527Z',
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

