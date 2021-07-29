<?php
namespace LaunchDarkly\Tests;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\FeatureFlag;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Impl\EventFactory;

class EventFactoryTest extends \PHPUnit\Framework\TestCase
{
    private function buildFlag($trackEvents)
    {
        $vr = array(
            'rollout' => array(
                'variations' => array(
                    array('variation' => 0, 'weight' => 10000, 'untracked' => false),
                    array('variation' => 1, 'weight' => 20000, 'untracked' => false),
                    array('variation' => 0, 'weight' => 70000, 'untracked' => true)
                ),
                'kind' => 'experiment',
                // seed here carefully chosen so users fall into different variations
                'seed' => 61
            ),
            'clauses' => []

        );

        $flag = array(
            'key' => 'feature',
            'version' => 1,
            'deleted' => false,
            'on' => true,
            'targets' => array(),
            'prerequisites' => array(),
            'rules' => array(),
            'offVariation' => 1,
            'fallthrough' => $vr,
            'variations' => array('fall', 'off', 'on'),
            'salt' => 'saltyA',
            'trackEvents' => $trackEvents
        );
        $decodedFlag = call_user_func(FeatureFlag::getDecoder(), $flag);

        return $decodedFlag;
    }

    public function testTrackEventFalse()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough());

        $result = $ef->newEvalEvent($flag, $user, $detail, null);

        $this->assertFalse(isset($result['trackEvents']));
    }

    public function testTrackEventTrue()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(true);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough());

        $result = $ef->newEvalEvent($flag, $user, $detail, null);

        $this->assertTrue($result['trackEvents']);
    }

    public function testTrackEventTrueWhenTrackEventsFalseButExperimentFallthroughReason()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $detail = new EvaluationDetail('off', 1, EvaluationReason::fallthrough(true));

        $result = $ef->newEvalEvent($flag, $user, $detail, null);

        $this->assertTrue($result['trackEvents']);
    }

    public function testTrackEventTrueWhenTrackEventsFalseButExperimentRuleMatchReason()
    {
        $ef = new EventFactory(false);

        $flag = $this->buildFlag(false);
        $ub = new LDUserBuilder('userkey');
        $user = $ub->build();

        $detail = new EvaluationDetail('off', 1, EvaluationReason::ruleMatch(1, 'something', true));

        $result = $ef->newEvalEvent($flag, $user, $detail, null);

        $this->assertTrue($result['trackEvents']);
    }
}
