<?php
namespace LaunchDarkly;

interface FeatureRequester
{

    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature($key);

    /**
     * Gets segment data from a likely cached store
     *
     * @param $key string segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment($key);

    /**
     * Gets all features.
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures();
}
