<?php
namespace LaunchDarkly\Tests;

use InvalidArgumentException;
use LaunchDarkly\EvalResult;
use LaunchDarkly\FeatureFlag;
use LaunchDarkly\FeatureFlagsState;

class FeatureFlagsStateTest extends \PHPUnit_Framework_TestCase
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

	public function testCanGetFlagValue()
	{
		$flag = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
		$state = new FeatureFlagsState(true);
		$state->addFlag($flag, new EvalResult(0, 'value1', array()));

		$this->assertEquals('value1', $state->getFlagValue('key1'));
	}

	public function testUnknownFlagReturnsNullValue()
	{
		$state = new FeatureFlagsState(true);
		
		$this->assertNull($state->getFlagValue('key1'));
	}

	public function testCanConvertToValuesMap()
	{
		$flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
		$flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
		$state = new FeatureFlagsState(true);
		$state->addFlag($flag1, new EvalResult(0, 'value1', array()));
		$state->addFlag($flag2, new EvalResult(0, 'value2', array()));

		$expected = array('key1' => 'value1', 'key2' => 'value2');
		$this->assertEquals($expected, $state->toValuesMap());
	}

	public function testCanConvertToJson()
	{
		$flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
		$flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
		$state = new FeatureFlagsState(true);
		$state->addFlag($flag1, new EvalResult(0, 'value1', array()));
		$state->addFlag($flag2, new EvalResult(1, 'value2', array()));

        $expected = array(
            'key1' => 'value1',
            'key2' => 'value2',
            '$flagsState' => array(
                'key1' => array(
                	'variation' => 0,
                	'version' => 100,
                	'trackEvents' => false
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
		$state->addFlag($flag1, new EvalResult(0, 'value1', array()));
		$state->addFlag($flag2, new EvalResult(1, 'value2', array()));

		$expected = $state->jsonSerialize();
		$json = json_encode($state);
		$decoded = json_decode($json, true);
		$this->assertEquals($expected, $decoded);
	}
}
