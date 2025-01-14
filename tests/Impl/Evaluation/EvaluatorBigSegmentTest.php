<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\BigSegments\StoreManager;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\LDContext;
use LaunchDarkly\Subsystems\BigSegmentsStore;
use LaunchDarkly\Tests\BigSegmentsStoreImpl;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use LaunchDarkly\Types\BigSegmentConfig;
use LaunchDarkly\Types\BigSegmentsStoreMetadata;
use PHPUnit\Framework\TestCase;

$defaultContext = LDContext::create('foo');

class EvaluatorBigSegmentTest extends TestCase
{
    public function testExplicitIncludeContext(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [['test.g100' => true]]);

        $this->assertTrue(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testExplicitExcludeContext(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [['test.g100' => false]]);

        $this->assertFalse(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testImplicitExcludeContext(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();

        // The membership query is successful, but has no segment data. We consider this a miss.
        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [[]]);

        $this->assertFalse(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testMissingGenerationCausesMiss(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->unbounded(true)
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [['test.g100' => true]]);

        $this->assertFalse(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testWrongContextCausesMiss(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->unbounded(true)
            ->generation(100)
            ->unboundedContextKind('org')
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [['test.g100' => true]]);

        $this->assertFalse(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testCanQueryForMembershipMultipleTimes(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10), new BigSegmentsStoreMetadata(10)], [['test.g100' => false], ['test.g100' => true]]);

        $this->assertFalse(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
        $this->assertTrue(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    public function testMembershipIsRememberedForLengthOfEvaluation(): void
    {
        global $defaultContext;
        $segment1 = ModelBuilders::segmentBuilder('test1')
            ->generation(100)
            ->unbounded(true)
            ->build();
        $segment2 = ModelBuilders::segmentBuilder('test2')
            ->generation(300)
            ->unbounded(true)
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [['test1.g100' => true, 'test2.g300' => true], ['test1.g100' => true, 'test2.g300' => false]]);
        $evaluator = self::getEvaluator($store, $defaultContext, [$segment1, $segment2]);
        $flag = ModelBuilders::booleanFlagWithClauses(ModelBuilders::clauseMatchingSegment($segment1), ModelBuilders::clauseMatchingSegment($segment2));

        $detail = $evaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals())->getDetail();
        $this->assertTrue($detail->getValue());

        $detail = $evaluator->evaluate($flag, $defaultContext, EvaluatorTestUtil::expectNoPrerequisiteEvals())->getDetail();
        $this->assertFalse($detail->getValue());
    }

    public function testSegmentLogicFallsThroughToRules(): void
    {
        global $defaultContext;
        $segment = ModelBuilders::segmentBuilder('test')
            ->generation(100)
            ->unbounded(true)
            ->rule(
                ModelBuilders::segmentRuleBuilder()
                    ->clause(ModelBuilders::clauseMatchingContext($defaultContext))
                    ->weight(100000)
                    ->build()
            )
            ->build();

        $store = new BigSegmentsStoreImpl([new BigSegmentsStoreMetadata(10)], [null]);

        $this->assertTrue(self::bigSegmentsMatchesContext($store, $segment, $defaultContext));
    }

    private static function bigSegmentsMatchesContext(BigSegmentsStore $store, Segment $segment, LDContext $context): bool
    {
        $evaluator = self::getEvaluator($store, $context, [$segment]);
        $flag = ModelBuilders::booleanFlagWithClauses(ModelBuilders::clauseMatchingSegment($segment));

        $detail = $evaluator->evaluate($flag, $context, EvaluatorTestUtil::expectNoPrerequisiteEvals())->getDetail();
        if ($detail->getValue() === null) {
            self::assertTrue(false, "Evaluation failed with reason: " . json_encode($detail->getReason()));
        }
        return $detail->getValue();
    }

    private static function getEvaluator(BigSegmentsStore $store, LDContext $context, array $segments): Evaluator
    {
        $logger = EvaluatorTestUtil::testLogger();
        $config = new BigSegmentConfig(store: $store);
        $manager = new StoreManager(config: $config, logger: $logger);

        $requester = new MockFeatureRequester();
        foreach ($segments as $segment) {
            $requester->addSegment($segment);
        }
        return new Evaluator($requester, $manager, $logger);
    }
}
