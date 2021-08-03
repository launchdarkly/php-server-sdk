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

    public function getFeature(string $key): ?\LaunchDarkly\FeatureFlag
    {
        return ($key == $this->key) ? $this->val : null;
    }

    public function getSegment(string $key): ?\LaunchDarkly\Segment
    {
        return null;
    }

    public function getAllFeatures(): ?array
    {
        return null;
    }
}
