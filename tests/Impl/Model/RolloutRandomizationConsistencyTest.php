<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\EvalResult;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDUserBuilder;
use LaunchDarkly\Tests\MockFeatureRequester;
use PHPUnit\Framework\TestCase;

/**
  * Note: These tests are meant to be exact duplicates of tests
  * in other SDKs. Do not change any of the values unless they
  * are also changed in other SDKs. These are not traditional behavioral
  * tests so much as consistency tests to guarantee that the implementation
  * is identical across SDKs.
  */

class RolloutRandomizationConsistencyTest extends TestCase
{
    private static $requester;

    public static function setUpBeforeClass(): void
    {
        static::$requester = new MockFeatureRequester();
    }

    public function buildFlag()
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
            'salt' => 'saltyA'
        ];
        $decodedFlag = call_user_func(FeatureFlag::getDecoder(), $flag);

        return $decodedFlag;
    }
    
    public function testVariationIndexForUser()
    {
        $flag = $this->buildFlag();
        $eventFactory = new EventFactory(false);

        $evaluationReasonInExperiment = EvaluationReason::fallthrough(true);
        $evaluationReasonNotInExperiment = EvaluationReason::fallthrough(false);

        $expectedEvalResult1 = new EvalResult(
            new EvaluationDetail('fall', 0, $evaluationReasonInExperiment),
            []
        );

        $expectedEvalResult2 = new EvalResult(
            new EvaluationDetail('off', 1, $evaluationReasonInExperiment),
            []
        );

        $expectedEvalResult3 = new EvalResult(
            new EvaluationDetail('fall', 0, $evaluationReasonNotInExperiment),
            []
        );

        $ub1 = new LDUserBuilder('userKeyA');
        $user1 = $ub1->build();
        $result1 = $flag->evaluate($user1, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult1, $result1);

        $ub2 = new LDUserBuilder('userKeyB');
        $user2 = $ub2->build();
        $result2 = $flag->evaluate($user2, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult2, $result2);

        $ub3 = new LDUserBuilder('userKeyC');
        $user3 = $ub3->build();
        $result3 = $flag->evaluate($user3, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult3, $result3);
    }

    public function testBucketUserByKey()
    {
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $ub1 = new LDUserBuilder('userKeyA');
        $user1 = $ub1->build();
        $point1 = $decodedVr->bucketUser($user1, 'hashKey', 'key', 'saltyA', null);
        $difference1 = abs($point1 - 0.42157587);
        $this->assertTrue($difference1 <= 0.0000001);

        $ub2 = new LDUserBuilder('userKeyB');
        $user2 = $ub2->build();
        $point2 = $decodedVr->bucketUser($user2, 'hashKey', 'key', 'saltyA', null);
        $difference2 = abs($point2 - 0.6708485);
        $this->assertTrue($difference2 <= 0.0000001);

        $ub3 = new LDUserBuilder('userKeyC');
        $user3 = $ub3->build();
        $point3 = $decodedVr->bucketUser($user3, 'hashKey', 'key', 'saltyA', null);
        $difference3 = abs($point3 - 0.10343106);
        $this->assertTrue($difference3 <= 0.0000001);
    }

    public function testBucketUserBySeed()
    {
        $seed = 61;
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $ub1 = new LDUserBuilder('userKeyA');
        $user1 = $ub1->build();
        $point1 = $decodedVr->bucketUser($user1, 'hashKey', 'key', 'saltyA', $seed);
        $difference1 = abs($point1 - 0.09801207);
        $this->assertTrue($difference1 <= 0.0000001);

        $ub2 = new LDUserBuilder('userKeyB');
        $user2 = $ub2->build();
        $point2 = $decodedVr->bucketUser($user2, 'hashKey', 'key', 'saltyA', $seed);
        $difference2 = abs($point2 - 0.14483777);
        $this->assertTrue($difference2 <= 0.0000001);

        $ub3 = new LDUserBuilder('userKeyC');
        $user3 = $ub3->build();
        $point3 = $decodedVr->bucketUser($user3, 'hashKey', 'key', 'saltyA', $seed);
        $difference3 = abs($point3 - 0.9242641);
        $this->assertTrue($difference3 <= 0.0000001);
    }
}
