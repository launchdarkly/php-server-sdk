<?php

namespace LaunchDarkly\Tests\Impl\Model;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\EvalResult;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDContext;
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
    
    public function testVariationIndexForContext()
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

        $context1 = LDContext::create('userKeyA');
        $result1 = $flag->evaluate($context1, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult1, $result1);

        $context2 = LDContext::create('userKeyB');
        $result2 = $flag->evaluate($context2, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult2, $result2);

        $context3 = LDContext::create('userKeyC');
        $result3 = $flag->evaluate($context3, static::$requester, $eventFactory);
        $this->assertEquals($expectedEvalResult3, $result3);
    }

    public function testBucketContextByKey()
    {
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $context1 = LDContext::create('userKeyA');
        $point1 = $decodedVr->bucketContext($context1, 'hashKey', 'key', 'saltyA', null);
        $difference1 = abs($point1 - 0.42157587);
        $this->assertTrue($difference1 <= 0.0000001);

        $context2 = LDContext::create('userKeyB');
        $point2 = $decodedVr->bucketContext($context2, 'hashKey', 'key', 'saltyA', null);
        $difference2 = abs($point2 - 0.6708485);
        $this->assertTrue($difference2 <= 0.0000001);

        $context3 = LDContext::create('userKeyC');
        $point3 = $decodedVr->bucketContext($context3, 'hashKey', 'key', 'saltyA', null);
        $difference3 = abs($point3 - 0.10343106);
        $this->assertTrue($difference3 <= 0.0000001);
    }

    public function testBucketContextBySeed()
    {
        $seed = 61;
        $vr = ['rollout' => [
            'variations' => [
                ['variation' => 1, 'weight' => 50000],
                ['variation' => 2, 'weight' => 50000]
            ]
        ]];

        $decodedVr = call_user_func(VariationOrRollout::getDecoder(), $vr);

        $context1 = LDContext::create('userKeyA');
        $point1 = $decodedVr->bucketContext($context1, 'hashKey', 'key', 'saltyA', $seed);
        $difference1 = abs($point1 - 0.09801207);
        $this->assertTrue($difference1 <= 0.0000001);

        $context2 = LDContext::create('userKeyB');
        $point2 = $decodedVr->bucketContext($context2, 'hashKey', 'key', 'saltyA', $seed);
        $difference2 = abs($point2 - 0.14483777);
        $this->assertTrue($difference2 <= 0.0000001);

        $context3 = LDContext::create('userKeyC');
        $point3 = $decodedVr->bucketContext($context3, 'hashKey', 'key', 'saltyA', $seed);
        $difference3 = abs($point3 - 0.9242641);
        $this->assertTrue($difference3 <= 0.0000001);
    }
}
