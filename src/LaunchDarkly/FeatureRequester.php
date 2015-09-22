<?php
namespace LaunchDarkly;

interface FeatureRequester {

    /**
     * Gets feature data from a likely cached store
     *
     * @param $key string feature key
     * @return mixed|null The decoded JSON feature data, or null if missing
     */
    public function get($key);
}