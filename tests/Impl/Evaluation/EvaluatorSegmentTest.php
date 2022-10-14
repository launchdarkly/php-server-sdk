<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\EvaluatorBucketing;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;

$defaultContext = LDContext::create('foo');

class EvaluatorSegmentTest extends TestCase
{
    public function testExplicitIncludeContext()
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')->included([$defaultContext->getKey()])->build();
        $this->assertTrue(self::segmentMatchesContext($segment, $defaultContext));
    }

    public function testExplicitExcludeContext()
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->rule(ModelBuilders::segmentRuleMatchingContext($defaultContext))
            ->excluded([$defaultContext->getKey()])
            ->build();
        $this->assertFalse(self::segmentMatchesContext($segment, $defaultContext));
    }

    public function testExplicitIncludeHasPrecedence()
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->included([$defaultContext->getKey()])->excluded([$defaultContext->getKey()])
            ->build();
        $this->assertTrue(self::segmentMatchesContext($segment, $defaultContext));
    }

    public function testMatchingRuleWithFullRollout()
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clauseMatchingContext($defaultContext))
                    ->weight(100000)
                    ->build()
            )
            ->build();
        $this->assertTrue(self::segmentMatchesContext($segment, $defaultContext));
    }

    public function testMatchingRuleWithZeroRollout()
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clauseMatchingContext($defaultContext))
                    ->weight(0)
                    ->build()
            )
            ->build();
        $this->assertFalse(self::segmentMatchesContext($segment, $defaultContext));
    }

    public function testRolloutCalculationCanBucketByKey()
    {
        $context = LDContext::builder('userkey')->name('Bob')->build();
        $this->verifyRollout($context, $context, 12551, 'test', 'salt', null, null);
    }

    public function testRolloutCalculationCanBucketBySpecificAttribute()
    {
        $context = LDContext::builder('userkey')->name('Bob')->build();
        $this->verifyRollout($context, $context, 61691, 'test', 'salt', 'name', null);
    }

    private function verifyRollout(
        LDContext $evalContext,
        LDContext $matchContext,
        int $expectedBucketValue,
        string $segmentKey,
        string $salt,
        ?string $bucketBy,
        ?string $rolloutContextKind
    ) {
        $segmentShouldMatch = ModelBuilders::segmentBuilder($segmentKey)
            ->salt($salt)
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clauseMatchingContext($matchContext))
                    ->weight($expectedBucketValue + 1)
                    ->bucketBy($bucketBy)
                    ->rolloutContextKind($rolloutContextKind)
                    ->build()
            )
            ->build();
        $segmentShouldNotMatch = ModelBuilders::segmentBuilder($segmentKey)
            ->salt($salt)
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clauseMatchingContext($matchContext))
                    ->weight($expectedBucketValue)
                    ->bucketBy($bucketBy)
                    ->rolloutContextKind($rolloutContextKind)
                    ->build()
            )
            ->build();
        $this->assertTrue($this->segmentMatchesContext($segmentShouldMatch, $evalContext));
        $this->assertFalse($this->segmentMatchesContext($segmentShouldNotMatch, $evalContext));
    }

    public function testMatchingRuleWithMultipleClauses()
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clause(null, 'email', 'in', 'test@example.com'))
                    ->clause(ModelBuilders::clause(null, 'name', 'in', 'bob'))
                    ->build()
            )
            ->build();
        $context = LDContext::builder('foo')->name('bob')->set('email', 'test@example.com')->build();
        $this->assertTrue(self::segmentMatchesContext($segment, $context));
    }

    public function testRolloutUsesContextKind()
    {
        $context1 = LDContext::create('key1', 'kind1');
        $context2 = LDContext::create('key2', 'kind2');
        $multi = LDContext::createMulti($context1, $context2);
        $expectedBucketValue = (int)(100000 *
            EvaluatorBucketing::getBucketValueForContext($context2, 'kind2', 'test', 'key', 'salt', null));
        $this->verifyRollout($multi, $context2, $expectedBucketValue, 'test', 'salt', null, 'kind2');
    }

    public function testNonMatchingRuleWithMultipleClauses()
    {
        $segment = ModelBuilders::segmentBuilder('test')
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clause(null, 'email', 'in', 'test@example.com'))
                    ->clause(ModelBuilders::clause(null, 'name', 'in', 'bill'))
                    ->build()
            )
            ->build();
        $context = LDContext::builder('foo')->name('bob')->set('email', 'test@example.com')->build();
        $this->assertFalse(self::segmentMatchesContext($segment, $context));
    }

    private static function segmentMatchesContext(Segment $segment, LDContext $context): bool
    {
        $flag = ModelBuilders::booleanFlagWithClauses(ModelBuilders::clauseMatchingSegment($segment));

        $requester = new MockFeatureRequester();
        $requester->addSegment($segment);
        $evaluator = new Evaluator($requester);

        return $evaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals())->getDetail()->getValue();
    }
}
