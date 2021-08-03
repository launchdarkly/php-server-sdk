<?php
namespace LaunchDarkly;

/**
 * Internal class used in LDClient.
 *
 * @ignore
 * @internal
 */
class PreloadedFeatureRequester implements FeatureRequester
{
    /** @var FeatureRequester */
    private $_baseRequester;

    /** @var array */
    private $_knownFeatures;

    public function __construct(FeatureRequester $baseRequester, array $knownFeatures)
    {
        $this->_baseRequester = $baseRequester;
        $this->_knownFeatures = $knownFeatures;
    }

    /**
     * Gets feature data from cached values
     *
     * @param $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        if (isset($this->_knownFeatures[$key])) {
            return $this->_knownFeatures[$key];
        }
        return null;
    }

    /**
     * Gets segment data from the regular feature requester
     *
     * @param $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        return $this->_baseRequester->getSegment($key);
    }

    /**
     * Gets all features from cached values
     *
     * @return array|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        return $this->_knownFeatures;
    }
}
