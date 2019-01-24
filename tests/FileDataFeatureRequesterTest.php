<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\FileDataFeatureRequester;
use LaunchDarkly\LDUser;

class FileDataFeatureRequesterTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadsFile()
    {
        $fr = new FileDataFeatureRequester("./tests/filedata/all-properties.json");
        $flag1 = $fr->getFeature("flag1");
        $this->assertEquals("flag1", $flag1->getKey());
        $flag2 = $fr->getFeature("flag2");
        $this->assertEquals("flag2", $flag2->getKey());
        $seg1 = $fr->getSegment("seg1");
        $this->assertEquals("seg1", $seg1->getKey());
    }

    public function testLoadsMultipleFiles()
    {
        $fr = new FileDataFeatureRequester(array("./tests/filedata/flag-only.json",
            "./tests/filedata/segment-only.json"));
        $flag1 = $fr->getFeature("flag1");
        $this->assertEquals("flag1", $flag1->getKey());
        $seg1 = $fr->getSegment("seg1");
        $this->assertEquals("seg1", $seg1->getKey());
    }

    public function testShortcutFlagCanBeEvaluated()
    {
        $fr = new FileDataFeatureRequester("./tests/filedata/all-properties.json");
        $flag2 = $fr->getFeature("flag2");
        $this->assertEquals("flag2", $flag2->getKey());
        $result = $flag2->evaluate(new LDUser("user"), null);
        $this->assertEquals("value2", $result->getDetail()->getValue());
    }
}
