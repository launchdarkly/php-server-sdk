<?php
namespace LaunchDarkly\Tests;

require_once 'vendor/autoload.php';

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
        $this->assertEquals("jim", $client->toggle('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->toggle('foo', $user, 'jim'));
    }

    public function testGetApc() {
        $redis = new \Predis\Client(array(
                                        "scheme" => "tcp",
                                        "host" => 'localhost',
                                        "port" => 6379));
        $client = new LDClient("BOGUS_API_KEY", array('feature_requester_class' => '\\LaunchDarkly\\ApcLDDFeatureRequester',
            'apc_expiration' => 1));
        $builder = new LDUserBuilder(3);
        $user = $builder->build();

        $redis->del("launchdarkly:features");
        $this->assertEquals("jim", $client->toggle('foo', $user, 'jim'));
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "bar"));
        $this->assertEquals("bar", $client->toggle('foo', $user, 'jim'));

        # cached value so not updated
        $redis->hset("launchdarkly:features", 'foo', $this->gen_feature("foo", "baz"));
        $this->assertEquals("bar", $client->toggle('foo', $user, 'jim'));

        apc_delete("launchdarkly:features.foo");
        $this->assertEquals("baz", $client->toggle('foo', $user, 'jim'));
    }

    private function gen_feature($key, $val) {
        $data = <<<EOF
           {"name": "Feature $key", "key": "$key", "kind": "flag", "salt": "Zm9v", "on": true,
            "variations": [{"value": "$val", "weight": 100,
                            "targets": [{"attribute": "key", "op": "in", "values": []}],
                            "userTarget": {"attribute": "key", "op": "in", "values": []}},
                           {"value": false, "weight": 0,
                            "targets": [{"attribute": "key", "op": "in", "values": []}],
                            "userTarget": {"attribute": "key", "op": "in", "values": []}}],
            "commitDate": "2015-09-08T21:24:16.712Z",
            "creationDate": "2015-09-08T21:06:16.527Z",
            "version": 4}
EOF;
         return $data;
    }

}

