<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Subsystems\FeatureRequester;

/**
 * A mock implementation of FeatureRequester holding preconfigured flags/segments. If
 * we expect the test to query a nonexistent flag/segment, we must specify that ahead
 * of time with expectQueryForUnknown[Flag/Segment]; otherwise such a query throws an
 * exception causing the test to fail.
 */
class MockFeatureRequester implements FeatureRequester
{
    private $_flags = [];
    private $_segments = [];

    public function __construct($baseurl = '', $key = '', $options = [])
    {
    }

    public function addFlag(FeatureFlag $flag): MockFeatureRequester
    {
        $this->_flags[$flag->getKey()] = $flag;
        return $this;
    }

    public function addSegment(Segment $segment): MockFeatureRequester
    {
        $this->_segments[$segment->getKey()] = $segment;
        return $this;
    }

    public function expectQueryForUnknownFlag(string $key): MockFeatureRequester
    {
        $this->_flags[$key] = false;
        return $this;
    }

    public function expectQueryForUnknownSegment(string $key): MockFeatureRequester
    {
        $this->_segments[$key] = false;
        return $this;
    }

    public function getFeature(string $key): ?FeatureFlag
    {
        if (!isset($this->_flags[$key])) {
            throw new \InvalidArgumentException("test unexpectedly tried to get flag key: $key");
        }
        $ret = $this->_flags[$key];
        return $ret === false ? null : $ret;
    }

    public function getSegment(string $key): ?Segment
    {
        if (!isset($this->_segments[$key])) {
            throw new \InvalidArgumentException("test unexpectedly tried to get segment key: $key");
        }
        $ret = $this->_segments[$key];
        return $ret === false ? null : $ret;
    }

    public function getAllFeatures(): ?array
    {
        $ret = [];
        foreach ($this->_flags as $k => $v) {
            if ($v !== false) {
                $ret[$k] = $v;
            }
        }
        return $ret;
    }
}
