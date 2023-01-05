<?php

namespace LaunchDarkly\Tests\Integrations;

use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Integrations\Files;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class FileDataFeatureRequesterTest extends TestCase
{
    public function testLoadsFile()
    {
        $fr = Files::featureRequester("./tests/filedata/all-properties.json");
        $flag1 = $fr->getFeature("flag1");
        $this->assertEquals("flag1", $flag1->getKey());
        $flag2 = $fr->getFeature("flag2");
        $this->assertEquals("flag2", $flag2->getKey());
        $seg1 = $fr->getSegment("seg1");
        $this->assertEquals("seg1", $seg1->getKey());
    }

    public function testLoadsMultipleFiles()
    {
        $fr = Files::featureRequester(["./tests/filedata/flag-only.json",
            "./tests/filedata/segment-only.json"]);
        $flag1 = $fr->getFeature("flag1");
        $this->assertEquals("flag1", $flag1->getKey());
        $seg1 = $fr->getSegment("seg1");
        $this->assertEquals("seg1", $seg1->getKey());
    }

    public function testShortcutFlagCanBeEvaluated()
    {
        $fr = Files::featureRequester("./tests/filedata/all-properties.json");
        $flag2 = $fr->getFeature("flag2");
        $this->assertEquals("flag2", $flag2->getKey());
        $evaluator = new Evaluator($fr);
        $result = $evaluator->evaluate($flag2, LDContext::create("user"), null);
        $this->assertEquals("value2", $result->getDetail()->getValue());
    }
}
