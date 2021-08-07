<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;

class MockFeatureRequester implements FeatureRequester
{
    public static $flags = array();

    public function __construct($baseurl = '', $key = '', $options = array())
    {
    }

    public function getFeature(string $key): ?FeatureFlag
    {
        return self::$flags[$key] ?? null;
    }

    public function getSegment(string $key): ?Segment
    {
        return null;
    }

    public function getAllFeatures(): ?array
    {
        return self::$flags;
    }
}
