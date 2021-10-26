<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\EvaluationReason;

class EvaluationReasonTest extends \PHPUnit\Framework\TestCase
{
    public function testOffReasonSerialization()
    {
        $reason = EvaluationReason::off();
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'OFF'], json_decode($json, true));
        $this->assertEquals('OFF', (string)$reason);
    }

    public function testFallthroughReasonSerialization()
    {
        $reason = EvaluationReason::fallthrough();
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'FALLTHROUGH'], json_decode($json, true));
        $this->assertEquals('FALLTHROUGH', (string)$reason);
    }

    public function testFallthroughReasonNotInExperimentSerialization()
    {
        $reason = EvaluationReason::fallthrough(false);
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'FALLTHROUGH'], json_decode($json, true));
        $this->assertEquals('FALLTHROUGH', (string)$reason);
    }

    public function testFallthroughReasonInExperimentSerialization()
    {
        $reason = EvaluationReason::fallthrough(true);
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'FALLTHROUGH', 'inExperiment' => true], json_decode($json, true));
        $this->assertEquals('FALLTHROUGH', (string)$reason);
    }

    public function testTargetMatchReasonSerialization()
    {
        $reason = EvaluationReason::targetMatch();
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'TARGET_MATCH'], json_decode($json, true));
        $this->assertEquals('TARGET_MATCH', (string)$reason);
    }

    public function testRuleMatchReasonSerialization()
    {
        $reason = EvaluationReason::ruleMatch(0, 'id');
        $json = json_encode($reason);
        $this->assertEquals(
            ['kind' => 'RULE_MATCH', 'ruleIndex' => 0, 'ruleId' => 'id'],
            json_decode($json, true)
        );
        $this->assertEquals('RULE_MATCH(0,id)', (string)$reason);
    }

    public function testRuleMatchReasonNotInExperimentSerialization()
    {
        $reason = EvaluationReason::ruleMatch(0, 'id', false);
        $json = json_encode($reason);
        $this->assertEquals(
            ['kind' => 'RULE_MATCH', 'ruleIndex' => 0, 'ruleId' => 'id'],
            json_decode($json, true)
        );
        $this->assertEquals('RULE_MATCH(0,id)', (string)$reason);
    }

    public function testRuleMatchReasonInExperimentSerialization()
    {
        $reason = EvaluationReason::ruleMatch(0, 'id', true);
        $json = json_encode($reason);
        $this->assertEquals(
            ['kind' => 'RULE_MATCH', 'ruleIndex' => 0, 'ruleId' => 'id', 'inExperiment' => true],
            json_decode($json, true)
        );
        $this->assertEquals('RULE_MATCH(0,id)', (string)$reason);
    }

    public function testPrerequisiteFailedReasonSerialization()
    {
        $reason = EvaluationReason::prerequisiteFailed('key');
        $json = json_encode($reason);
        $this->assertEquals(
            ['kind' => 'PREREQUISITE_FAILED', 'prerequisiteKey' => 'key'],
            json_decode($json, true)
        );
        $this->assertEquals('PREREQUISITE_FAILED(key)', (string)$reason);
    }

    public function testErrorReasonSerialization()
    {
        $reason = EvaluationReason::error(EvaluationReason::EXCEPTION_ERROR);
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'ERROR', 'errorKind' => 'EXCEPTION'], json_decode($json, true));
        $this->assertEquals('ERROR(EXCEPTION)', (string)$reason);
    }
}
