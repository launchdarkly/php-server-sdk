<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Integrations;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Subsystems\FeatureRequester;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @ignore
 * @internal
 */
class FeatureRequesterBase implements FeatureRequester
{
    const FEATURES_NAMESPACE = 'features';
    const SEGMENTS_NAMESPACE = 'segments';
    const ALL_ITEMS_KEY = '$all';
    const CACHE_PREFIX = 'launchdarkly:';

    protected string $_baseUri;
    protected string $_sdkKey;
    protected array $_options;
    protected ?FeatureRequesterCache $_cache;
    protected LoggerInterface $_logger;

    protected function __construct(string $baseUri, string $sdkKey, array $options)
    {
        $this->_baseUri = $baseUri;
        $this->_sdkKey = $sdkKey;
        $this->_options = $options;
        $this->_cache = $this->createCache($options);

        if ($options['logger'] ?? null) {
            $this->_logger = $options['logger'];
        } else {
            $this->_logger = new NullLogger();
        }
    }

    /**
     * Override this method to read a JSON object (as a string) from the underlying store.
     *
     * @param string $namespace "features" or "segments"
     * @param string $key flag or segment key
     * @return string|null the stored JSON data, or null if not found
     */
    protected function readItemString(string $namespace, string $key): ?string
    {
        return null;
    }

    /**
     * Override this method to read a set of JSON objects (as strings) from the underlying store.
     *
     * @param string $namespace "features" or "segments"
     * @return array|null array of stored JSON strings
     */
    protected function readItemStringList(string $namespace): ?array
    {
        return [];
    }

    /**
     * Determines the caching implementation to use, if any.
     *
     * @return FeatureRequesterCache a cache implementation, or null
     */
    protected function createCache(array $options): ?FeatureRequesterCache
    {
        $expiration = (int)($options['apc_expiration'] ?? 0);
        return ($expiration > 0) ? new ApcuFeatureRequesterCache($expiration) : null;
    }

    /**
     * Gets an individual feature flag.
     *
     * @param string $key feature flag key
     * @return FeatureFlag|null The decoded JSON feature data, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
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
     * @param string $key segment key
     * @return Segment|null The decoded JSON segment data, or null if missing
     */
    public function getSegment(string $key): ?Segment
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
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        $jsonList = $this->getJsonItemList(self::FEATURES_NAMESPACE);
        $itemsOut = [];
        foreach ($jsonList as $json) {
            $flag = FeatureFlag::decode($json);
            if (!$flag->isDeleted()) {
                $itemsOut[$flag->getKey()] = $flag;
            }
        }
        return $itemsOut;
    }

    protected function getJsonItem(string $namespace, string $key): ?array
    {
        $cacheKey = $this->makeCacheKey($namespace, $key);
        $raw = $this->_cache?->getCachedString($cacheKey);
        if ($raw === null) {
            $raw = $this->readItemString($namespace, $key);
            $this->_cache?->putCachedString($cacheKey, $raw);
        }
        return ($raw === null) ? null : json_decode($raw, true);
    }

    protected function getJsonItemList(string $namespace): array
    {
        $cacheKey = $this->makeCacheKey($namespace, self::ALL_ITEMS_KEY);
        $raw = $this->_cache?->getCachedString($cacheKey);
        if ($raw) {
            $values = json_decode($raw, true);
        } else {
            $values = $this->readItemStringList($namespace);
            if (!$values) {
                $values = [];
            }
            $this->_cache?->putCachedString($cacheKey, json_encode($values));
        }
        foreach ($values as $i => $s) {
            $values[$i] = json_decode($s, true);
        }
        return $values;
    }

    private function makeCacheKey(string $namespace, string $key): string
    {
        return self::CACHE_PREFIX . $namespace . ':' . $key;
    }
}
