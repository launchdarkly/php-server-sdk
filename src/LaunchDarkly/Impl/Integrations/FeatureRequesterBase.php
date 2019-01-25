<?php
namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\FeatureFlag;
use LaunchDarkly\Segment;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FeatureRequesterBase implements \LaunchDarkly\FeatureRequester
{
    const FEATURES_NAMESPACE = 'features';
    const SEGMENTS_NAMESPACE = 'segments';
    const ALL_ITEMS_KEY = '$all';
    const CACHE_PREFIX = 'launchdarkly:';

    /** @var string */
    protected $_baseUri;
    /** @var string */
    protected $_sdkKey;
    /** @var array */
    protected $_options;
    /** @var FeatureRequesterCache */
    protected $_cache;
    /** @var LoggerInterface */
    protected $_logger;

    protected function __construct($baseUri, $sdkKey, $options)
    {
        $this->_baseUri = $baseUri;
        $this->_sdkKey = $sdkKey;
        $this->_options = $options;
        $this->_cache = $this->createCache($options);

        if (isset($options['logger']) && $options['logger']) {
            $this->_logger = $options['logger'];
        } else {
            $this->_logger = new NullLogger();
        }
    }

    /**
     * Override this method to read a JSON object (as a string) from the underlying store.
     *
     * @param $namespace "features" or "segments"
     * @param $key flag or segment key
     * @return string|null the stored JSON data, or null if not found
     */
    protected function readItemString($namespace, $key)
    {
        return null;
    }

    /**
     * Override this method to read a set of JSON objects (as strings) from the underlying store.
     *
     * @param $namespace "features" or "segments"
     * @return array|null array of stored JSON strings
     */
    protected function readItemStringList($namespace)
    {
        return array();
    }

    /**
     * Determines the caching implementation to use, if any.
     *
     * @return FeatureRequesterCache a cache implementation, or null
     */
    protected function createCache($options)
    {
        $expiration = isset($options['apc_expiration']) ? (int)$options['apc_expiration'] : 0;
        return ($expiration > 0) ? new ApcuFeatureRequesterCache($expiration) : null;
    }

    /**
     * Gets an individual feature flag.
     *
     * @param $key string feature flag key
     * @return FeatureFlag|null The decoded JSON feature data, or null if missing
     */
    public function getFeature($key)
    {
        $json = $this->getJsonItem(self::FEATURES_NAMESPACE, $key);
        if ($json) {
            $flag = FeatureFlag::decode($json);
            if ($flag->isDeleted()) {
                $this->_logger->warning("FeatureRequester: Attempted to get deleted feature with key: " . $key);
                return null;
            }
            return $flag;
        } else {
            $this->_logger->warning("FeatureRequester: Attempted to get missing feature with key: " . $key);
            return null;
        }
    }

    /**
     * Gets an individual user segment.
     *
     * @param $key string segment key
     * @return Segment|null The decoded JSON segment data, or null if missing
     */
    public function getSegment($key)
    {
        $json = $this->getJsonItem(self::SEGMENTS_NAMESPACE, $key);
        if ($json) {
            $segment = Segment::decode($json);
            if ($segment->isDeleted()) {
                $this->_logger->warning("FeatureRequester: Attempted to get deleted segment with key: " . $key);
                return null;
            }
            return $segment;
        } else {
            $this->_logger->warning("FeatureRequester: Attempted to get missing segment with key: " . $key);
            return null;
        }
    }

    /**
     * Gets all features
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures()
    {
        $jsonList = $this->getJsonItemList(self::FEATURES_NAMESPACE);
        $itemsOut = array();
        foreach ($jsonList as $json) {
            $flag = FeatureFlag::decode($json);
            if ($flag && !$flag->isDeleted()) {
                $itemsOut[$flag->getKey()] = $flag;
            }
        }
        return $itemsOut;
    }

    protected function getJsonItem($namespace, $key)
    {
        $cacheKey = $this->makeCacheKey($namespace, $key);
        $raw = $this->_cache ? $this->_cache->getCachedString($cacheKey) : null;
        if ($raw === null) {
            $raw = $this->readItemString($namespace, $key);
            if ($this->_cache) {
                $this->_cache->putCachedString($cacheKey, $raw);
            }
        }
        return ($raw === null) ? null : json_decode($raw, true);
    }

    protected function getJsonItemList($namespace)
    {
        $cacheKey = $this->makeCacheKey($namespace, self::ALL_ITEMS_KEY);
        $raw = $this->_cache ? $this->_cache->getCachedString($cacheKey) : null;
        if ($raw) {
            $values = json_decode($raw, true);
        } else {
            $values = $this->readItemStringList($namespace);
            if (!$values) {
                $values = array();
            }
            if ($this->_cache) {
                $this->_cache->putCachedString($cacheKey, json_encode(values));
            }
        }
        foreach ($values as $i => $s) {
            $values[$i] = json_decode($s, true);
        }
        return $values;
    }

    private function makeCacheKey($namespace, $key)
    {
        return self::CACHE_PREFIX . $namespace . ':' . $key;
    }
}
