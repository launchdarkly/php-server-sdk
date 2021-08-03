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

    public function getFeature(string $key): ?\LaunchDarkly\FeatureFlag
    {
        return null;
    }

    public function getSegment(string $key): ?\LaunchDarkly\Segment
    {
        return ($key == $this->key) ? $this->val : null;
    }

    public function getAllFeatures(): ?array
    {
        return null;
    }
}
