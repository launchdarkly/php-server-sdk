<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\EvaluatorBucketing;
use LaunchDarkly\Impl\Model\Rollout;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;

const RULE_ID = 'ruleid';

$defaultContext = LDContext::create('foo');

// This is our way of verifying that the bucket value for a rollout is within 1.0 of the expected value.
function makeRolloutVariations($targetValue, $targetVariation, $otherVariation)
{
    return [
        ModelBuilders::weightedVariation($otherVariation, $targetValue),
        ModelBuilders::weightedVariation($targetVariation, 1),
        ModelBuilders::weightedVariation($otherVariation, 100000 - ($targetValue + 1))
    ];
}

class EvaluatorFlagTest extends TestCase
{
    private static Evaluator $basicEvaluator;

    public static function setUpBeforeClass(): void
    {
        static::$basicEvaluator = EvaluatorTestUtil::basicEvaluator();
    }

    public function testFlagReturnsOffVariationIfFlagIsOff()
    {
        $flag = ModelBuilders::flagBuilder('feature')->variations('fall', 'off', 'on')
            ->on(false)->offVariation(1)->fallthroughVariation(0)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail('off', 1, EvaluationReason::off());
        self::assertEquals($detail, $result->getDetail());
        self::assertFalse($result->isForceReasonTracking());
    }

    public function testFlagReturnsNullIfFlagIsOffAndOffVariationIsUnspecified()
    {
        $flag = ModelBuilders::flagBuilder('feature')->variations('fall', 'off', 'on')
            ->on(false)->fallthroughVariation(0)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::off());
        self::assertEquals($detail, $result->getDetail());
        self::assertFalse($result->isForceReasonTracking());
    }

    public function testFlagReturnsErrorIfOffVariationIsTooHigh()
    {
        $flag = ModelBuilders::flagBuilder('feature')->variations('fall', 'off', 'on')
            ->on(false)->offVariation(999)->fallthroughVariation(0)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsErrorIfOffVariationIsNegative()
    {
        $flag = ModelBuilders::flagBuilder('feature')->variations('fall', 'off', 'on')
            ->on(false)->offVariation(-1)->fallthroughVariation(0)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagMatchesContextFromRules()
    {
        global $defaultContext;
        $flag = ModelBuilders::booleanFlagWithRules(
            ModelBuilders::flagRuleBuilder()
                ->id(RULE_ID)
                ->variation(1)
                ->clause(ModelBuilders::clauseMatchingContext($defaultContext))
                ->build()
        );

        $result = static::$basicEvaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsErrorIfRuleVariationIsTooHigh()
    {
        global $defaultContext;
        $flag = ModelBuilders::booleanFlagWithRules(ModelBuilders::flagRuleMatchingContext(999, $defaultContext));

        $result = static::$basicEvaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsErrorIfRuleVariationIsNegative()
    {
        global $defaultContext;
        $flag = ModelBuilders::booleanFlagWithRules(ModelBuilders::flagRuleMatchingContext(-1, $defaultContext));

        $result = static::$basicEvaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsErrorIfRuleHasNoVariationOrRollout()
    {
        global $defaultContext;
        $flag = ModelBuilders::booleanFlagWithRules(
            ModelBuilders::flagRuleBuilder()->clause(ModelBuilders::clauseMatchingContext($defaultContext))->build()
        );

        $result = static::$basicEvaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsErrorIfRuleHasRolloutWithNoVariations()
    {
        global $defaultContext;
        $rollout = new Rollout([], null);
        $flag = ModelBuilders::booleanFlagWithRules(
            ModelBuilders::flagRuleBuilder()->clause(ModelBuilders::clauseMatchingContext($defaultContext))
                ->rollout($rollout)->build()
        );

        $result = static::$basicEvaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testRolloutSelectsBucket()
    {
        $context = LDContext::create('userkey');
        $flagKey = 'flagkey';
        $salt = 'salt';
        
        // First verify that with our test inputs, the bucket value will be greater than zero and less than 100000,
        // so we can construct a rollout whose second bucket just barely contains that value
        $bucketValue = floor(EvaluatorBucketing::getBucketValueForContext($context, null, $flagKey, "key", $salt, null) * 100000);
        self::assertGreaterThan(0, $bucketValue);
        self::assertLessThan(100000, $bucketValue);

        $badVariationA = 0;
        $matchedVariation = 1;
        $badVariationB = 2;
        $rollout = new Rollout(
            [
                ModelBuilders::weightedVariation($badVariationA, $bucketValue), // end of bucket range is not inclusive, so it will *not* match the target value
                ModelBuilders::weightedVariation($matchedVariation, 1), // size of this bucket is 1, so it only matches that specific value
                ModelBuilders::weightedVariation($badVariationB, 100000 - ($bucketValue + 1))
            ],
            null
        );
        $flag = ModelBuilders::flagBuilder($flagKey)->on(true)->variations('', '', '')
            ->fallthroughRollout($rollout)
            ->salt($salt)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        self::assertSame($matchedVariation, $result->getDetail()->getVariationIndex());
    }

    public function testRolloutSelectsLastBucketIfBucketValueEqualsTotalWeight()
    {
        $context = LDContext::create('userkey');
        $flagKey = 'flagkey';
        $salt = 'salt';
        
        $bucketValue = floor(EvaluatorBucketing::getBucketValueForContext($context, null, $flagKey, "key", $salt, null) * 100000);

        // We'll construct a list of variations that stops right at the target bucket value
        $rollout = ModelBuilders::rolloutWithVariations(
            ModelBuilders::weightedVariation(0, $bucketValue)
        );
        $flag = ModelBuilders::flagBuilder($flagKey)->on(true)->variations('', '', '')
            ->fallthroughRollout($rollout)
            ->salt($salt)
            ->build();

        $result = static::$basicEvaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        self::assertSame(0, $result->getDetail()->getVariationIndex());
    }

    public function testRolloutCalculationBucketsByContextKeyByDefault()
    {
        $context = LDContext::create('userkey');
        $expectedBucketValue = 22464;
        $rollout = new Rollout(makeRolloutVariations($expectedBucketValue, 1, 0), null);
        $flag = ModelBuilders::booleanFlagWithRules(
            ModelBuilders::flagRuleBuilder()
                ->id(RULE_ID)
                ->clause(ModelBuilders::clauseMatchingContext($context))
                ->rollout($rollout)
                ->build()
        );

        $result = static::$basicEvaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testRolloutCalculationCanBucketBySpecificAttribute()
    {
        $context = LDContext::builder('userkey')->name('Bob')->build();
        $expectedBucketValue = 95913;
        $rollout = new Rollout(makeRolloutVariations($expectedBucketValue, 1, 0), 'name');
        $flag = ModelBuilders::booleanFlagWithRules(
            ModelBuilders::flagRuleBuilder()
                ->id(RULE_ID)
                ->clause(ModelBuilders::clauseMatchingContext($context))
                ->rollout($rollout)
                ->build()
        );

        $result = static::$basicEvaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail(true, 1, EvaluationReason::ruleMatch(0, RULE_ID));
        self::assertEquals($detail, $result->getDetail());
    }
}
