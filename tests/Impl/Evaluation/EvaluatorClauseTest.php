<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;

class EvaluatorClauseTest extends TestCase
{
    private static Evaluator $basicEvaluator;

    public static function setUpBeforeClass(): void
    {
        static::$basicEvaluator = EvaluatorTestUtil::basicEvaluator();
    }

    private function assertMatch(Evaluator $eval, FeatureFlag $flag, LDContext $context, bool $expectMatch)
    {
        $result = $eval->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        self::assertEquals($expectMatch, $result->getDetail()->getValue());
    }

    private function assertMatchClause(Evaluator $eval, Clause $clause, LDContext $context, bool $expectMatch)
    {
        self::assertMatch($eval, ModelBuilders::booleanFlagWithClauses($clause), $context, $expectMatch);
    }
      
    public function testClauseCanMatchBuiltInAttribute()
    {
        $clause = ModelBuilders::clause(null, 'name', 'in', 'Bob');
        $context = LDContext::builder('key')->name('Bob')->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, true);
    }

    public function testClauseCanMatchCustomAttribute()
    {
        $clause = ModelBuilders::clause(null, 'legs', 'in', 4);
        $context = LDContext::builder('key')->set('legs', 4)->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, true);
    }

    public function testClauseReturnsFalseForMissingAttribute()
    {
        $clause = ModelBuilders::clause(null, 'legs', 'in', 4);
        $context = LDContext::create('key');

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, false);
    }

    public function testClauseMatchesContextValueToAnyOfMultipleValues()
    {
        $clause = ModelBuilders::clause(null, 'name', 'in', 'Bob', 'Carol');
        $context = LDContext::builder('key')->name('Carol')->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, true);
    }

    public function testClauseMatchesArrayOfContextValuesToClauseValue()
    {
        $clause = ModelBuilders::clause(null, 'alias', 'in', 'Maurice');
        $context = LDContext::builder('key')->set('alias', ['Space Cowboy', 'Maurice'])->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, true);
    }

    public function testClauseFindsNoMatchInArrayOfContextValues()
    {
        $clause = ModelBuilders::clause(null, 'alias', 'in', 'Ma');
        $context = LDContext::builder('key')->set('alias', ['Mary', 'May'])->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, false);
    }

    public function testClauseCanBeNegatedToReturnFalse()
    {
        $clause = ModelBuilders::negate(ModelBuilders::clause(null, 'name', 'in', 'Bob'));
        $context = LDContext::builder('key')->name('Bob')->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, false);
    }

    public function testClauseCanBeNegatedToReturnTrue()
    {
        $clause = ModelBuilders::negate(ModelBuilders::clause(null, 'name', 'in', 'Rob'));
        $context = LDContext::builder('key')->name('Bob')->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, true);
    }

    public function testClauseWithUnknownOperatorDoesNotMatch()
    {
        $clause = ModelBuilders::clause(null, 'name', 'doesSomethingUnsupported', 'Bob');
        $context = LDContext::builder('key')->name('Bob')->build();

        self::assertMatchClause(static::$basicEvaluator, $clause, $context, false);
    }

    public function testClauseMatchUsesContextKind()
    {
        $clause = ModelBuilders::clause('company', 'name', 'in', 'Catco');
        $context1 = LDContext::builder('cc')->kind('company')->name('Catco')->build();
        $context2 = LDContext::builder('l')->name('Lucy')->build();
        $context3 = LDContext::createMulti($context1, $context2);

        self::assertMatchClause(static::$basicEvaluator, $clause, $context1, true);
        self::assertMatchClause(static::$basicEvaluator, $clause, $context2, false);
        self::assertMatchClause(static::$basicEvaluator, $clause, $context3, true);
    }

    public function testClauseMatchByKindAttribute()
    {
        $clause = ModelBuilders::clause(null, 'kind', 'startsWith', 'a');
        $context1 = LDContext::create('key');
        $context2 = LDContext::create('key', 'ab');
        $context3 = LDContext::createMulti(
            LDContext::create('key', 'cd'),
            LDContext::create('key', 'ab')
        );

        self::assertMatchClause(static::$basicEvaluator, $clause, $context1, false);
        self::assertMatchClause(static::$basicEvaluator, $clause, $context2, true);
        self::assertMatchClause(static::$basicEvaluator, $clause, $context3, true);
    }

    public function testSegmentMatchClauseRetrievesSegmentFromStore()
    {
        $context = LDContext::create('key');
        $segment = ModelBuilders::segmentBuilder('segkey')->included($context->getKey())->build();
        $requester = new MockFeatureRequester();
        $requester->addSegment($segment);
        $evaluator = new Evaluator($requester);

        $clause = ModelBuilders::clauseMatchingSegment($segment);

        self::assertMatchClause($evaluator, $clause, $context, true);
    }

    public function testSegmentMatchClauseFallsThroughWithNoErrorsIfSegmentNotFound()
    {
        $context = LDContext::create('key');
        $requester = new MockFeatureRequester();
        $requester->expectQueryForUnknownSegment('segkey');
        $evaluator = new Evaluator($requester);
        
        $clause = ModelBuilders::clause(null, '', 'segmentMatch', 'segkey');

        self::assertMatchClause($evaluator, $clause, $context, false);
    }
}
