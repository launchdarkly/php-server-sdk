<?php
namespace LaunchDarkly;

use Predis\ClientInterface;
use Psr\Log\LoggerInterface;

class LDDFeatureRequester implements FeatureRequester
{
    protected $_baseUri;
    protected $_sdkKey;
    protected $_options;
    protected $_features_key;
    protected $_segments_key;
    /** @var  LoggerInterface */
    private $_logger;
    /** @var  ClientInterface */
    private $_connection;

    public function __construct($baseUri, $sdkKey, $options)
    {
        $this->_baseUri = $baseUri;
        $this->_sdkKey = $sdkKey;
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
        $this->_segments_key = "$prefix:segments";
        $this->_logger = $options['logger'];

        if (isset($this->_options['predis_client']) && $this->_options['predis_client'] instanceof ClientInterface) {
            $this->_connection = $this->_options['predis_client'];
        }
    }

    /**
     * @return ClientInterface
     */
    protected function get_connection()
    {
        if ($this->_connection instanceof ClientInterface) {
            return $this->_connection;
        }
        
        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection */
        return $this->_connection = new \Predis\Client(array(
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
    public function getFeature($key)
    {
        $raw = $this->get_from_cache($this->_features_key, $key);
        if ($raw === null) {
            $redis = $this->get_connection();
            $raw = $redis->hget($this->_features_key, $key);
            if ($raw) {
                $this->store_in_cache($this->_features_key, $key, $raw);
            }
        }
        if ($raw) {
            $flag = FeatureFlag::decode(json_decode($raw, true));
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
     * Gets segment data from a likely cached store
     *
     * @param $key string segment key
     * @return Segment|null The decoded JSON segment data, or null if missing
     */
    public function getSegment($key)
    {
        $raw = $this->get_from_cache(this->_segments_key, $key);
        if ($raw === null) {
            $redis = $this->get_connection();
            $raw = $redis->hget($this->_features_key, $key);
            if ($raw) {
                $this->store_in_cache(this->_segments_key, $key, $raw);
            }
        }
        if ($raw) {
            $segment = Segment::decode(json_decode($raw, true));
            if ($segment->isDeleted()) {
                $this->_logger->warning("LDDFeatureRequester: Attempted to get deleted segment with key: " . $key);
                return null;
            }
            return $segment;
        } else {
            $this->_logger->warning("LDDFeatureRequester: Attempted to get missing segment with key: " . $key);
            return null;
        }
    }

    /**
     * Gets the value from local cache. No-op by default.
     * @param $namespace string that denotes features or segments
     * @param $key string The feature or segment key
     * @return null|array The feature or segment data or null if missing
     */
    protected function get_from_cache($namespace, $key)
    {
        return null;
    }

    /**
     * Stores the feature or segment data into the local cache.  No-op by default.
     * @param $namespace string that denotes features or segments
     * @param $key string The feature or segment key
     * @param $val array The feature or segment data
     */
    protected function store_in_cache($namespace, $key, $val)
    {
    }

    /**
     * Gets all features
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures()
    {
        $redis = $this->get_connection();
        $raw = $redis->hgetall($this->_features_key);
        if ($raw) {
            $allFlags = array_map(FeatureFlag::getDecoder(), $this->decodeFeatures($raw));
            /**
             * @param $flag FeatureFlag
             * @return bool
             */
            $isNotDeleted = function ($flag) {
                return !$flag->isDeleted();
            };
            return array_filter($allFlags, $isNotDeleted);
        } else {
            $this->_logger->warning("LDDFeatureRequester: Attempted to get all features, instead got nothing.");
            return null;
        }
    }

    /**
     * @param array $features
     *
     * @return array
     */
    private function decodeFeatures(array $features)
    {
        foreach ($features as $featureKey => $feature) {
            $features[$featureKey] = json_decode($feature, true);
        }

        return $features;
    }
}
