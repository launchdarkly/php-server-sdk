<?php
namespace LaunchDarkly;


use Psr\Log\LoggerInterface;

class LDDFeatureRequester implements FeatureRequester {
    protected $_baseUri;
    protected $_apiKey;
    protected $_options;
    protected $_features_key;
    /** @var  LoggerInterface */
    private $_logger;

    function __construct($baseUri, $apiKey, $options) {
        $this->_baseUri = $baseUri;
        $this->_apiKey = $apiKey;
        if (!isset($options['redis_host'])) {
            $options['redis_host'] = 'localhost';
        }
        if (!isset($options['redis_port'])) {
            $options['redis_port'] = 6379;
        }

        $this->_options = $options;

        $prefix = "launchdarkly";
        if (isset($options['redis_prefix'])) {
            $prefix = $options['redis_prefix'];
        }
        $this->_features_key = "$prefix:features";
        $this->_logger = $options['logger'];

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
     * @return FeatureFlag|null The decoded JSON feature data, or null if missing
     */
    public function get($key) {
        $raw = $this->get_from_cache($key);
        if ($raw === null) {
            $redis = $this->get_connection();
            $raw = $redis->hget($this->_features_key, $key);
            if ($raw) {
                $this->store_in_cache($key, $raw);
            }
        }
        if ($raw) {
            $flag = FeatureFlag::decode(json_decode($raw, True));
            if ($flag->isDeleted()) {
                $this->_logger->warning("LDDFeatureRequester: Attempted to get deleted feature with key: " . $key);
                return null;
            }
            return $flag;

        } else {
            $this->_logger->warning("LDDFeatureRequester: Attempted to get missing feature with key: " . $key);
            return null;
        }
    }

    /**
     * Gets the value from local cache. No-op by default.
     * @param $key string The feature key
     * @return null|array The feature data or null if missing
     */
    protected function get_from_cache($key) {
        return null;
    }

    /**
     * Stores the feature data into the local cache.  No-op by default.
     * @param $key string The feature key
     * @param $val array The feature data
     */
    protected function store_in_cache($key, $val) {}
}