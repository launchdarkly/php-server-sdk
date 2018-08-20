<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureRequester;

class MockFeatureRequester implements FeatureRequester
{
    public static $flags = array();

    public function __construct($baseurl, $key, $options)
    {
    }

    public function getFeature($key)
    {
        return self::$flags[$key];
    }

    public function getSegment($key)
    {
        return null;
    }

    public function getAllFeatures()
    {
        return self::$flags;
    }
}
