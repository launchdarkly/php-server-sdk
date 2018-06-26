<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;

class MockFeatureRequesterForSegment implements FeatureRequester
{
    public $key = null;
    public $val = null;

    public function __construct($baseurl = null, $key = null, $options = null)
    {
    }

    public function getFeature($key)
    {
        return null;
    }

    public function getSegment($key)
    {
        return ($key == $this->key) ? $this->val : null;
    }

    public function getAllFeatures()
    {
        return null;
    }
}
