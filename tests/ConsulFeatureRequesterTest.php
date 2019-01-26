<?php

namespace LaunchDarkly\Tests;

use \LaunchDarkly\Integrations\Consul;
use SensioLabs\Consul\Exception\ClientException;
use SensioLabs\Consul\ServiceFactory;

class ConsulFeatureRequesterTest extends FeatureRequesterTestBase
{
    const TABLE_NAME = 'test-table';
    const PREFIX = 'test';

    private static $kvClient;

    public static function setUpBeforeClass()
    {
        $sf = new ServiceFactory();
        self::$kvClient = $sf->get('kv');
    }

    protected function makeRequester()
    {
        $options = array(
            'consul_prefix' => self::PREFIX
        );
        $factory = Consul::featureRequester();
        return $factory('', '', $options);
    }

    protected function putItem($namespace, $key, $version, $json)
    {
        self::$kvClient->put(self::PREFIX . '/' . $namespace . '/' . $key, $json);
    }

    protected function deleteExistingData()
    {
        try {
            $resp = self::$kvClient->get(self::PREFIX . '/', array('keys' => true, 'recurse' => true));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return;
            }
            throw $e;
        }
        $results = $resp->json();
        foreach ($results as $key) {
            self::$kvClient->delete($key);
        }
    }
}
