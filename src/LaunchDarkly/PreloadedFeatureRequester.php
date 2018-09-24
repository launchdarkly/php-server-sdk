<?php
namespace LaunchDarkly;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Psr\Log\LoggerInterface;

class PreloadedFeatureRequester implements FeatureRequester
{
    /** @var FeatureRequester */
    private $_baseRequester;

    /** @var array */
    private $_knownFeatures;

    public function __construct($baseRequester, $knownFeatures)
    {
        $this->_baseRequester = $baseRequester;
        $this->_knownFeatures = $knownFeatures;
    }

    /**
     * Gets feature data from cached values
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature($key)
    {
        if (isset($this->_knownFeatures[$key])) {
            return $this->_knownFeatures[$key];
        }
        return null;
    }

    /**
     * Gets segment data from the regular feature requester
     *
     * @param $key string segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment($key)
    {
        return $this->_baseRequester->getSegment($key);
    }

    /**
     * Gets all features from cached values
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures()
    {
        return $this->_knownFeatures;
    }
}
