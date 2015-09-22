<?php
namespace LaunchDarkly;


class LDDFeatureRequester implements FeatureRequester {
    protected $_baseUri;
    protected $_apiKey;
    protected $_options;
    protected $_features_key;

    function __construct($baseUri, $apiKey, $options) {
        $this->_baseUri = $baseUri;
        $this->_apiKey = $apiKey;
        $this->_options = $options;
        if (!isset($options['redis_host'])) {
            $options['redis_host'] = 'localhost';
        }
        if (!isset($options['redis_port'])) {
            $options['redis_port'] = 6379;
        }

        $prefix = "launchdarkly";
        if (isset($options['redis_prefix'])) {
            $prefix = $options['redis_prefix'];
        }
        $this->_features_key = "$prefix:features";
    }

    protected function get_connection() {
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        return new \Predis\Client(array(
                                      "scheme" => "tcp",
                                      "host" => $this->_options['redis_host'],
                                      "port" => $this->_options['redis_port']));
    }


    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return mixed The decoded JSON feature data, or null if missing
     */
    public function get($key) {
        $redis = $this->get_connection();
        $raw = $redis->hget($this->_features_key, $key);
        if ($raw) {
            return json_decode($raw);
        }
        else {
            return null;
        }
    }
}