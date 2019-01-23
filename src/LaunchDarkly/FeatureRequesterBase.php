<?php
namespace LaunchDarkly;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FeatureRequesterBase implements FeatureRequester
{
    protected const FEATURES_NAMESPACE = 'features';
    protected const SEGMENTS_NAMESPACE = 'segments';
    private const ALL_ITEMS_KEY = '$all';
    private const CACHE_PREFIX = 'LaunchDarkly:';

    /** @var string */
    protected $_baseUri;
    /** @var string */
    protected $_sdkKey;
    /** @var array */
    protected $_options;
    /** @var int */
    protected $_apcExpiration;
    /** @var LoggerInterface */
    protected $_logger;

    protected function __construct($baseUri, $sdkKey, $options)
    {
        $this->_baseUri = $baseUri;
        $this->_sdkKey = $sdkKey;
        $this->_options = $options;

        if (isset($options['apc_expiration'])) {
            if (!extension_loaded('apcu')) {
                throw new \InvalidArgumentException('apc_expiration was specified but apcu is not installed');
            }
            $this->_apcExpiration = (int)$options['apc_expiration'];
        } else {
            $this->_apcExpiration = 0;
        }

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

    protected function getCachedString($namespace, $key)
    {
        if ($this->_apcExpiration) {
            $value = \apc_fetch($this->makeCacheKey($namespace, $key));
            return $value === false ? null : $value;
        }
        return null;
    }

    protected function putCachedString($namespace, $key, $data)
    {
        if ($this->_apcExpiration) {
            \apc_add($this->makeCacheKey($namespace, $key), $data, $this->_apcExpiration);
        }
    }

    protected function makeCacheKey($namespace, $key) {
        return self::CACHE_PREFIX . $namespace . ':' . $key;
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
        $raw = $this->getCachedString($namespace, $key);
        if ($raw === null) {
            $raw = $this->readItemString($namespace, $key);
            $this->putCachedString($namespace, $key, $raw);
        }
        return ($raw === null) ? null : json_decode($raw, true);
    }

    protected function getJsonItemList($namespace)
    {
        $raw = $this->getCachedString($namespace, self::ALL_ITEMS_KEY);
        if ($raw) {
            $values = json_decode($raw, true);
        } else {
            $values = $this->readItemStringList($namespace);
            $this->putCachedString($namespace, self::ALL_ITEMS_KEY, json_encode($values));
        }
        foreach ($values as $i => $s) {
            $values[$i] = json_decode($s, true);
        }
        return $values;
    }
}
