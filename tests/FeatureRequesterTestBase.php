<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\FeatureFlag;
use LaunchDarkly\Segment;

class FeatureRequesterTestBase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->deleteExistingData();
    }

    protected function deleteExistingData()
    {
    }

    protected function makeRequester()
    {
    }

    protected function putItem($namespace, $key, $version, $json)
    {
    }

    public function testGetFeature()
    {
        $flagKey = 'foo';
        $flagVersion = 10;
        $flagJson = self::makeFlagJson($flagKey, $flagVersion);
        $this->putItem('features', $flagKey, $flagVersion, $flagJson);

        $fr = $this->makeRequester();
        $flag = $fr->getFeature($flagKey);

        $this->assertInstanceOf(FeatureFlag::class, $flag);
        $this->assertEquals($flagVersion, $flag->getVersion());
    }

    public function testGetMissingFeature()
    {
        $fr = $this->makeRequester();
        $flag = $fr->getFeature('unavailable');
        $this->assertNull($flag);
    }

    public function testGetDeletedFeature()
    {
        $flagKey = 'foo';
        $flagVersion = 10;
        $flagJson = self::makeFlagJson($flagKey, $flagVersion, true);
        $this->putItem('features', $flagKey, $flagVersion, $flagJson);

        $fr = $this->makeRequester();
        $flag = $fr->getFeature($flagKey);

        $this->assertNull($flag);
    }

    public function testGetAllFeatures()
    {
        $flagKey1 = 'foo';
        $flagKey2 = 'bar';
        $flagKey3 = 'deleted';
        $flagVersion = 10;
        $flagJson1 = self::makeFlagJson($flagKey1, $flagVersion);
        $flagJson2 = self::makeFlagJson($flagKey2, $flagVersion);
        $flagJson3 = self::makeFlagJson($flagKey3, $flagVersion, true);

        $this->putItem('features', $flagKey1, $flagVersion, $flagJson1);
        $this->putItem('features', $flagKey2, $flagVersion, $flagJson2);
        $this->putItem('features', $flagKey3, $flagVersion, $flagJson3);

        $fr = $this->makeRequester();
        $flags = $fr->getAllFeatures();
        
        $this->assertEquals(2, count($flags));
        $flag1 = $flags[$flagKey1];
        $this->assertEquals($flagKey1, $flag1->getKey());
        $this->assertEquals($flagVersion, $flag1->getVersion());
        $flag2 = $flags[$flagKey2];
        $this->assertEquals($flagKey2, $flag2->getKey());
        $this->assertEquals($flagVersion, $flag2->getVersion());
    }

    public function testGetSegment()
    {
        $segKey = 'foo';
        $segVersion = 10;
        $segJson = self::makeSegmentJson($segKey, $segVersion);
        $this->putItem('segments', $segKey, $segVersion, $segJson);

        $fr = $this->makeRequester();
        $segment = $fr->getSegment($segKey);

        $this->assertInstanceOf(Segment::class, $segment);
        $this->assertEquals($segVersion, $segment->getVersion());
    }

    public function testGetMissingSegment()
    {
        $fr = $this->makeRequester();
        $segment = $fr->getSegment('unavailable');
        $this->assertNull($segment);
    }

    public function testGetDeletedSegment()
    {
        $segKey = 'foo';
        $segVersion = 10;
        $segJson = self::makeSegmentJson($segKey, $segVersion, true);
        $this->putItem('segments', $segKey, $segVersion, $segJson);

        $fr = $this->makeRequester();
        $segment = $fr->getSegment($segKey);

        $this->assertNull($segment);
    }

    private static function makeFlagJson($key, $version, $deleted = false)
    {
        return json_encode(array(
            'key' => $key,
            'version' => $version,
            'on' => true,
            'prerequisites' => [],
            'salt' => '',
            'targets' => [],
            'rules' => [],
            'fallthrough' => [
                'variation' => 0,
            ],
            'offVariation' => null,
            'variations' => [
                true,
                false,
            ],
            'deleted' => $deleted
        ));
    }

    private static function makeSegmentJson($key, $version, $deleted = false)
    {
        return json_encode(array(
            'key' => $key,
            'version' => $version,
            'included' => array(),
            'excluded' => array(),
            'rules' => [],
            'salt' => '',
            'deleted' => $deleted
        ));
    }
}
