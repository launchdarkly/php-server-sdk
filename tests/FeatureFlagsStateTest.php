<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\FeatureFlagsState;
use LaunchDarkly\Impl\Model\FeatureFlag;

class FeatureFlagsStateTest extends \PHPUnit\Framework\TestCase
{
    private static $flag1Json = [
        'key' => 'key1',
        'version' => 100,
        'deleted' => false,
        'on' => false,
        'targets' => [],
        'prerequisites' => [],
        'rules' => [],
        'offVariation' => 0,
        'fallthrough' => ['variation' => 0],
        'variations' => ['value1'],
        'salt' => '',
        'trackEvents' => false
    ];
    private static $flag2Json = [
        'key' => 'key2',
        'version' => 200,
        'deleted' => false,
        'on' => false,
        'targets' => [],
        'prerequisites' => [],
        'rules' => [],
        'offVariation' => 0,
        'fallthrough' => ['variation' => 0],
        'variations' => ['value2'],
        'salt' => '',
        'trackEvents' => true,
        'debugEventsUntilDate' => 1000
    ];

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

        $expected = ['key1' => 'value1', 'key2' => 'value2'];
        $this->assertEquals($expected, $state->toValuesMap());
    }

    public function testCanConvertToJson()
    {
        $flag1 = FeatureFlag::decode(FeatureFlagsStateTest::$flag1Json);
        $flag2 = FeatureFlag::decode(FeatureFlagsStateTest::$flag2Json);
        $state = new FeatureFlagsState(true);
        $state->addFlag($flag1, new EvaluationDetail('value1', 0, self::irrelevantReason()));
        $state->addFlag($flag2, new EvaluationDetail('value2', 1, self::irrelevantReason()));

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            '$flagsState' => [
                'key1' => [
                    'variation' => 0,
                    'version' => 100
                ],
                'key2' => [
                    'variation' => 1,
                    'version' => 200,
                    'trackEvents' => true,
                    'debugEventsUntilDate' => 1000
                ]
            ],
            '$valid' => true
        ];
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

    public function testJsonEncodeWithEmptyData()
    {
        $state = new FeatureFlagsState(true);
        $json = json_encode($state);

        $expected = [
            '$valid' => true,
            '$flagsState' => []
        ];
        $this->assertEquals($expected, json_decode($json, true));

        // Due to ambiguity of PHP array types, we need to verify that the $flagsState value
        // is an empty JSON object, not an empty JSON array.
        $this->assertStringContainsString('"$flagsState":{}', $json);
    }
}
