<?php

namespace LaunchDarkly\Tests\Impl\Events;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Evaluation\EvalResult;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDContext;
use PHPUnit\Framework\TestCase;

class EventFactoryTest extends TestCase
{
    private function buildFlag($trackEvents)
    {
        $vr = [
            'rollout' => [
                'variations' => [
                    ['variation' => 0, 'weight' => 10000, 'untracked' => false],
                    ['variation' => 1, 'weight' => 20000, 'untracked' => false],
                    ['variation' => 0, 'weight' => 70000, 'untracked' => true]
                ],
                'kind' => 'experiment',
                // seed here carefully chosen so users fall into different variations
                'seed' => 61
            ],
            'clauses' => []

        ];

        $flag = [
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => [],
            'prerequisites' => [],
            'rules' => [],
            'offVariation' => 1,
            'fallthrough' => $vr,
            'variations' => ['fall', 'off', 'on'],
            'salt' => 'saltyA',
            'trackEvents' => $trackEvents
        ];
        $decodedFlag = call_user_func(FeatureFlag::getDecoder(), $flag);

        return $decodedFlag;
    }

    public function testTrackEventFalse()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $context = LDContext::create('userkey');

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough());

        $result = $ef->newEvalEvent($flag, $context, new EvalResult($detail, false), null);

        $this->assertFalse(isset($result['trackEvents']));
    }

    public function testTrackEventTrue()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(true);
        $context = LDContext::create('userkey');

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough());

        $result = $ef->newEvalEvent($flag, $context, new EvalResult($detail, false), null);

        $this->assertTrue($result['trackEvents']);
    }

    public function testTrackEventTrueWhenTrackEventsFalseButExperimentFallthroughReason()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $context = LDContext::create('userkey');

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough(true));

        $result = $ef->newEvalEvent($flag, $context, new EvalResult($detail, true), null);

        $this->assertTrue($result['trackEvents']);
    }

    public function testTrackEventTrueWhenTrackEventsFalseButExperimentRuleMatchReason()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $context = LDContext::create('userkey');

        $detail = new EvaluationDetail('off', 1, EvaluationReason::ruleMatch(1, 'something', true));

        $result = $ef->newEvalEvent($flag, $context, new EvalResult($detail, true), null);

        $this->assertTrue($result['trackEvents']);
    }
}
