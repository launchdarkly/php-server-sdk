<?php
namespace LaunchDarkly\Tests;

require_once 'vendor/autoload.php';

use LaunchDarkly\ApcLDDFeatureRequester;
use LaunchDarkly\ApcuLDDFeatureRequester;
use LaunchDarkly\LDClient;
use LaunchDarkly\LDUserBuilder;

class LDDFeatureRetrieverTest extends \PHPUnit_Framework_TestCase {

    public function testGet() {
        $redis = new \Predis\Client(array(
                                      "scheme" => "tcp",
                                      "host" => 'localhost',
                                      "port" => 6379));
        $client = new LDClient("BOGUS_API_KEY", array('feature_requester_class' => '\\LaunchDarkly\\LDDFeatureRequester'));
        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->variation('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));
    }

    public function testGetApc() {
        $redis = new \Predis\Client(array(
                                        "scheme" => "tcp",
                                        "host" => 'localhost',
                                        "port" => 6379));
        $client = new LDClient("BOGUS_API_KEY", [
            'feature_requester_class' => extension_loaded('apcu') ? ApcuLDDFeatureRequester::class : ApcLDDFeatureRequester::class,
            'apc_expiration' => 1,
        ]);
        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->variation('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));

        # cached value so not updated
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "baz"));
        $this->assertEquals("bar", $client->variation('foo', $user, 'jim'));

        if (extension_loaded('apcu')) {
            \apcu_delete("launchdarkly:features.foo");
        } else {
            \apc_delete("launchdarkly:features.foo");
        }
        $this->assertEquals("baz", $client->variation('foo', $user, 'jim'));
    }

    private function gen_feature($key, $val) {
        $data = [
            'name' => 'Feature ' . $val,
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

