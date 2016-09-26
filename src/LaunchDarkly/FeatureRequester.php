<?php
namespace LaunchDarkly;

interface FeatureRequester {

    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function get($key);

    /**
     * Gets all features.
     *
     * @return array()|null The decoded FeatureFlags, or null if missing
     */
    public function getAll();
}