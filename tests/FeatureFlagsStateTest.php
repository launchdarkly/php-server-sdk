<?php
namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\FeatureFlagsState;
use LaunchDarkly\Impl\Model\FeatureFlag;
use PHPUnit\Framework\TestCase;

class FeatureFlagsStateTest extends \PHPUnit\Framework\TestCase
{
    private static $flag1Json = array(
        'key' => 'key1',
        'version' => 100,
        'deleted' => false,
        'on' => false,
        'targets' => array(),
        'prerequisites' => array(),
        'rules' => array(),
        'offVariation' => 0,
        'fallthrough' => array('variation' => 0),
        'variations' => array('value1'),
        'salt' => '',
        'trackEvents' => false
    );
    private static $flag2Json = array(
        'key' => 'key2',
        'version' => 200,
        'deleted' => false,
        'on' => false,
        'targets' => array(),
        'prerequisites' => array(),
        'rules' => array(),
        'offVariation' => 0,
        'fallthrough' => array('variation' => 0),
        'variations' => array('value2'),
        'salt' => '',
        'trackEvents' => true,
        'debugEventsUntilDate' => 1000
    );
    
    private static function irrelevantReason(): EvaluationReason
    {
        return EvaluationReason::off();
    }

    public function testCanGetFlagValue()
    {
        $flag = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag, new EvaluationDetail('value1', 0, self::irrelevantReason()));

        $this->assertEquals('value1', $state->getFlagValue('key1'));
    }

    public function testUnknownFlagReturnsNullValue()
    {
        $state = new FeatureFlagsState(true);
        
        $this->assertNull($state->getFlagValue('key1'));
    }

    public function testCanGetFlagReason()
    {
        $flag = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag, new EvaluationDetail('value1', 0, EvaluationReason::off()), true);

        $this->assertEquals(EvaluationReason::off(), $state->getFlagReason('key1'));
    }

    public function testUnknownFlagReturnsNullReason()
    {
        $state = new FeatureFlagsState(true);

        $this->assertNull($state->getFlagReason('key1'));
    }

    public function testReasonIsNullIfReasonsWereNotRecorded()
    {
        $flag = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag, new EvaluationDetail('value1', 0, EvaluationReason::off()), false);

        $this->assertNull($state->getFlagReason('key1'));
    }

    public function testCanConvertToValuesMap()
    {
        $flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag1, new EvaluationDetail('value1', 0, self::irrelevantReason()));
        $state->addFlag($flag2, new EvaluationDetail('value2', 0, self::irrelevantReason()));

        $expected = array('key1' => 'value1', 'key2' => 'value2');
        $this->assertEquals($expected, $state->toValuesMap());
    }

    public function testCanConvertToJson()
    {
        $flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag1, new EvaluationDetail('value1', 0, self::irrelevantReason()));
        $state->addFlag($flag2, new EvaluationDetail('value2', 1, self::irrelevantReason()));

        $expected = array(
            'key1' => 'value1',
            'key2' => 'value2',
            '$flagsState' => array(
                'key1' => array(
                    'variation' => 0,
                    'version' => 100
                ),
                'key2' => array(
                    'variation' => 1,
                    'version' => 200,
                    'trackEvents' => true,
                    'debugEventsUntilDate' => 1000
                )
            ),
            '$valid' => true
        );
        $this->assertEquals($expected, $state->jsonSerialize());
    }

    public function testJsonEncodeUsesCustomSerializer()
    {
        $flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag1, new EvaluationDetail('value1', 0, self::irrelevantReason()));
        $state->addFlag($flag2, new EvaluationDetail('value2', 1, self::irrelevantReason()));

        $expected = $state->jsonSerialize();
        $json = json_encode($state);
        $decoded = json_decode($json, true);
        $this->assertEquals($expected, $decoded);
    }
}
