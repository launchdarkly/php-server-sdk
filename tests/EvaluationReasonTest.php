<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\BigSegmentsEvaluationStatus;
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

    public function testErrorReasonRetainsExceptionWithoutSerializingIt()
    {
        $exception = new \RuntimeException('sensitive diagnostic details');
        $reason = EvaluationReason::exception($exception);

        $this->assertSame($exception, $reason->getException());
        $this->assertEquals(
            ['kind' => 'ERROR', 'errorKind' => 'EXCEPTION'],
            json_decode(json_encode($reason), true)
        );
        $this->assertEquals('ERROR(EXCEPTION)', (string)$reason);
    }

    public function testSerializeOmitsException()
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            // The closure argument is kept in the stack trace of the exception.
            $exception = (fn (callable $callback) => new \RuntimeException('store unavailable'))(fn () => null);
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
        $reason = EvaluationReason::exception($exception)
            ->withBigSegmentsEvaluationStatus(BigSegmentsEvaluationStatus::STALE);

        $restored = unserialize(serialize($reason));

        $this->assertNull($restored->getException());
        $this->assertEquals(EvaluationReason::ERROR, $restored->getKind());
        $this->assertEquals(EvaluationReason::EXCEPTION_ERROR, $restored->getErrorKind());
        $this->assertSame(BigSegmentsEvaluationStatus::STALE, $restored->bigSegmentsEvaluationStatus());
    }

    public function testSerializeKeepsReasonFields()
    {
        $reason = EvaluationReason::ruleMatch(1, 'id', true);

        $this->assertEquals($reason, unserialize(serialize($reason)));
    }

    public function testBigSegmentsStatusPreservesException()
    {
        $exception = new \RuntimeException('store unavailable');
        $reason = EvaluationReason::exception($exception)
            ->withBigSegmentsEvaluationStatus(BigSegmentsEvaluationStatus::STALE);

        $this->assertSame($exception, $reason->getException());
        $this->assertSame(BigSegmentsEvaluationStatus::STALE, $reason->bigSegmentsEvaluationStatus());
        $this->assertEquals(
            ['kind' => 'ERROR', 'errorKind' => 'EXCEPTION', 'bigSegmentsStatus' => 'STALE'],
            json_decode(json_encode($reason), true)
        );
    }

    public function testReasonsWithoutExceptionsReturnNull()
    {
        $reasons = [
            EvaluationReason::off(),
            EvaluationReason::fallthrough(),
            EvaluationReason::targetMatch(),
            EvaluationReason::ruleMatch(0, 'rule'),
            EvaluationReason::prerequisiteFailed('prerequisite'),
            EvaluationReason::error(EvaluationReason::FLAG_NOT_FOUND_ERROR),
            EvaluationReason::error(EvaluationReason::EXCEPTION_ERROR),
        ];

        foreach ($reasons as $reason) {
            $this->assertNull($reason->getException());
        }
    }

    public function testErrorReasonSerialization()
    {
        $reason = EvaluationReason::error(EvaluationReason::EXCEPTION_ERROR);
        $json = json_encode($reason);
        $this->assertEquals(['kind' => 'ERROR', 'errorKind' => 'EXCEPTION'], json_decode($json, true));
        $this->assertEquals('ERROR(EXCEPTION)', (string)$reason);
    }
}
