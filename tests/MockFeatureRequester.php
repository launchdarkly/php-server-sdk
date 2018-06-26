<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;

class MockFeatureRequester implements FeatureRequester
{
    public static $val = null;

    public function __construct($baseurl, $key, $options)
    {
    }

    public function getFeature($key)
    {
        return self::$val;
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
