<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;

class MockFeatureRequesterForFeature implements FeatureRequester
{
    public $key = null;
    public $val = null;

    public function __construct($baseurl = null, $key = null, $options = null)
    {
    }

    public function getFeature($key)
    {
        return ($key == $this->key) ? $this->val : null;
    }

    public function getSegment($key)
    {
        return null;
    }

    public function getAllFeatures()
    {
        return null;
    }
}
