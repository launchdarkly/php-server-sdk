<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\BigSegments\StoreManager;
use LaunchDarkly\Impl\Evaluation\EvalResult;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\EvaluatorBucketing;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Types\BigSegmentsConfig;
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

        $evaluationReasonInExperiment = EvaluationReason::fallthrough(true);
        $evaluationReasonNotInExperiment = EvaluationReason::fallthrough(false);

        $expectedEvalResult1 = new EvalResult(
            new EvaluationDetail('fall', 0, $evaluationReasonInExperiment),
            true
        );

        $expectedEvalResult2 = new EvalResult(
            new EvaluationDetail('off', 1, $evaluationReasonInExperiment),
            true
        );

        $expectedEvalResult3 = new EvalResult(
            new EvaluationDetail('fall', 0, $evaluationReasonNotInExperiment),
            false
        );

        $storeManager = new StoreManager(config: new BigSegmentsConfig(store: null), logger: EvaluatorTestUtil::testLogger());
        $evaluator = new Evaluator(static::$requester, $storeManager);

        $context1 = LDContext::create('userKeyA');
        $result1 = $evaluator->evaluate($flag, $context1, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $this->assertEquals($expectedEvalResult1->getDetail(), $result1->getDetail());
        $this->assertEquals($expectedEvalResult1->isForceReasonTracking(), $result1->isForceReasonTracking());

        $context2 = LDContext::create('userKeyB');
        $result2 = $evaluator->evaluate($flag, $context2, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $this->assertEquals($expectedEvalResult2->getDetail(), $result2->getDetail());
        $this->assertEquals($expectedEvalResult2->isForceReasonTracking(), $result2->isForceReasonTracking());

        $context3 = LDContext::create('userKeyC');
        $result3 = $evaluator->evaluate($flag, $context3, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $this->assertEquals($expectedEvalResult3->getDetail(), $result3->getDetail());
        $this->assertEquals($expectedEvalResult3->isForceReasonTracking(), $result3->isForceReasonTracking());
    }

    public function testBucketContextByKey()
    {
        $context1 = LDContext::create('userKeyA');
        $point1 = EvaluatorBucketing::getBucketValueForContext($context1, null, 'hashKey', 'key', 'saltyA', null);
        $difference1 = abs($point1 - 0.42157587);
        $this->assertTrue($difference1 <= 0.0000001);

        $context2 = LDContext::create('userKeyB');
        $point2 = EvaluatorBucketing::getBucketValueForContext($context2, null, 'hashKey', 'key', 'saltyA', null);
        $difference2 = abs($point2 - 0.6708485);
        $this->assertTrue($difference2 <= 0.0000001);

        $context3 = LDContext::create('userKeyC');
        $point3 = EvaluatorBucketing::getBucketValueForContext($context3, null, 'hashKey', 'key', 'saltyA', null);
        $difference3 = abs($point3 - 0.10343106);
        $this->assertTrue($difference3 <= 0.0000001);
    }

    public function testBucketContextBySeed()
    {
        $seed = 61;
        $context1 = LDContext::create('userKeyA');
        $point1 = EvaluatorBucketing::getBucketValueForContext($context1, null, 'hashKey', 'key', 'saltyA', $seed);
        $difference1 = abs($point1 - 0.09801207);
        $this->assertTrue($difference1 <= 0.0000001);

        $context2 = LDContext::create('userKeyB');
        $point2 = EvaluatorBucketing::getBucketValueForContext($context2, null, 'hashKey', 'key', 'saltyA', $seed);
        $difference2 = abs($point2 - 0.14483777);
        $this->assertTrue($difference2 <= 0.0000001);

        $context3 = LDContext::create('userKeyC');
        $point3 = EvaluatorBucketing::getBucketValueForContext($context3, null, 'hashKey', 'key', 'saltyA', $seed);
        $difference3 = abs($point3 - 0.9242641);
        $this->assertTrue($difference3 <= 0.0000001);
    }
}
