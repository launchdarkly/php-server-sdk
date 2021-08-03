<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;

class MockFeatureRequester implements FeatureRequester
{
    public static $flags = array();

    public function __construct($baseurl = '', $key = '', $options = array())
    {
    }

    public function getFeature(string $key): ?\LaunchDarkly\FeatureFlag
    {
        return self::$flags[$key] ?? null;
    }

    public function getSegment(string $key): ?\LaunchDarkly\Segment
    {
        return null;
    }

    public function getAllFeatures(): ?array
    {
        return self::$flags;
    }
}
