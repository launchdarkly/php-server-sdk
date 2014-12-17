<?php
namespace LaunchDarkly;

use \GuzzleHttp\Exception\BadResponseException;
use \GuzzleHttp\Subscriber\Cache\CacheSubscriber;

class LDClient {
    const DEFAULT_BASE_URI = 'https://app.launchdarkly.com';
    const VERSION = '0.1.0';

    protected $_apiKey;
    protected $_baseUri;
    protected $_client;

    public function __construct($apiKey, $options = []) {
        $this->_apiKey = $apiKey;
        $this->_baseUri = $options['base_uri'] ? rtrim($options['base_uri'], '/') : self::DEFAULT_BASE_URI;
        if (!isset($options['timeout'])) {
            $options['timeout'] = 3;
        }
        if (!isset($options['connect_timeout'])) {
            $options['connect_timeout'] = 3;
        }

        $this->_client = $this->_make_client($options);
    }

    public function getFlag($key, $user, $default = false) {
        try {
            $flag = $this->_getFlag($key, $user, $default);
            return is_null($flag) ? $default : $flag;
        } catch (Exception $e) {
            error_log("LaunchDarkly caught $e");
            return $default;
        }
    }

    protected function _getFlag($key, $user, $default) {
        try {
            $response = $this->_client->get("/api/eval/features/$key");
            return self::_decode($response->json(), $user);
        } catch (BadResponseException $e) {
            $code = $e->getResponse()->getStatusCode();
            error_log("LDClient::getFlag received HTTP status code $code, using default");
            return $default;
        }
    }

    protected function _make_client() {
        $client = new \GuzzleHttp\Client([
            'base_url' => $this->_baseUri,
            'defaults' => [
                'headers' => [
                    'Authorization' => "api_key {$this->_apiKey}",
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'PHPClient/' . VERSION
                ],
                'timeout'         => $options['timeout'],
                'connect_timeout' => $options['connect_timeout']
            ]
        ]);

        $csOptions = $options['cache_storage'] ? ['storage' => $options['cache_storage']] : [];
        CacheSubscriber::attach($client, $csOptions);
        return $client;
    }

    protected static function _decode($json, $user) {
        $makeVariation = function ($v) {
            $makeTarget = function ($t) {
                return new TargetRule($t['attribute'], $t['op'], $t['values']);
            };

            $ts = empty($v['targets']) ? [] : $v['targets'];
            $targets = array_map($makeTarget, $ts);
            return new Variation($v['value'], $v['weight'], $targets);
        };

        $vs = empty($json['variations']) ? [] : $json['variations'];
        $variations = array_map($makeVariation, $vs);
        $feature = new FeatureRep($json['name'], $json['key'], $json['salt'], $json['on'], $variations);

        return $feature->evaluate($user);
    }
}
