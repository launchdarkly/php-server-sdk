<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;

$defaultContext = LDContext::create('foo');

class EvaluatorPrerequisiteTest extends TestCase
{
    private static Evaluator $basicEvaluator;

    public static function setUpBeforeClass(): void
    {
        static::$basicEvaluator = EvaluatorTestUtil::basicEvaluator();
    }

    public function testFlagReturnsOffVariationIfPrerequisiteIsNotFound()
    {
        $flag = ModelBuilders::flagBuilder('feature0')->variations('fall', 'off', 'on')
            ->on(true)->offVariation(1)->fallthroughVariation(0)
            ->prerequisite('feature1', 1)
            ->build();

        $requester = new MockFeatureRequester();
        $requester->expectQueryForUnknownFlag('feature1');
        $evaluator = new Evaluator($requester);

        $result = $evaluator->evaluate($flag, LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed('feature1'));
        self::assertEquals($detail, $result->getDetail());
    }

    public function testFlagReturnsOffVariationAndEventIfPrerequisiteIsOff()
    {
        $flag1 = ModelBuilders::flagBuilder('feature1')->variations('nogo', 'go')
            ->on(false)->offVariation(1)->fallthroughVariation(0)
            // note that even though it returns the desired variation, it is still off and therefore not a match
            ->build();
        $flag0 = ModelBuilders::flagBuilder('feature0')->variations('fall', 'off', 'on')
            ->on(true)->offVariation(1)->fallthroughVariation(0)
            ->prerequisite($flag1->getKey(), 1)
            ->build();

        $requester = new MockFeatureRequester();
        $requester->addFlag($flag1);
        $evaluator = new Evaluator($requester);
        $recorder = EvaluatorTestUtil::prerequisiteRecorder();

        $result = $evaluator->evaluate($flag0, LDContext::create('user'), $recorder->record());
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed($flag1->getKey()));
        self::assertEquals($detail, $result->getDetail());

        self::assertEquals(1, count($recorder->evals));
        $eval = $recorder->evals[0];
        self::assertEquals($flag1, $eval->getFlag());
        self::assertEquals('go', $eval->getResult()->getDetail()->getValue());
        self::assertEquals($flag0, $eval->getPrereqOfFlag());
    }

    public function testFlagReturnsOffVariationAndEventIfPrerequisiteIsNotMet()
    {
        $flag1 = ModelBuilders::flagBuilder('feature1')->variations('nogo', 'go')
            ->on(true)->offVariation(1)->fallthroughVariation(0)
            ->build();
        $flag0 = ModelBuilders::flagBuilder('feature0')->variations('fall', 'off', 'on')
            ->on(true)->offVariation(1)->fallthroughVariation(0)
            ->prerequisite($flag1->getKey(), 1)
            ->build();

        $requester = new MockFeatureRequester();
        $requester->addFlag($flag1);
        $evaluator = new Evaluator($requester);
        $recorder = EvaluatorTestUtil::prerequisiteRecorder();

        $result = $evaluator->evaluate($flag0, LDContext::create('user'), $recorder->record());
        $detail = new EvaluationDetail('off', 1, EvaluationReason::prerequisiteFailed($flag1->getKey()));
        self::assertEquals($detail, $result->getDetail());

        self::assertEquals(1, count($recorder->evals));
        $eval = $recorder->evals[0];
        self::assertEquals($flag1, $eval->getFlag());
        self::assertEquals('nogo', $eval->getResult()->getDetail()->getValue());
        self::assertEquals($flag0, $eval->getPrereqOfFlag());
    }

    public function testFlagReturnsFallthroughVariationAndEventIfPrerequisiteIsMetAndThereAreNoRules()
    {
        $flag1 = ModelBuilders::flagBuilder('feature1')->variations('nogo', 'go')
            ->on(true)->offVariation(1)->fallthroughVariation(1)
            ->build();
        $flag0 = ModelBuilders::flagBuilder('feature0')->variations('fall', 'off', 'on')
            ->on(true)->fallthroughVariation(0)
            ->prerequisite($flag1->getKey(), 1)
            ->build();

        $requester = new MockFeatureRequester();
        $requester->addFlag($flag1);
        $evaluator = new Evaluator($requester);
        $recorder = EvaluatorTestUtil::prerequisiteRecorder();

        $result = $evaluator->evaluate($flag0, LDContext::create('user'), $recorder->record());
        $detail = new EvaluationDetail('fall', 0, EvaluationReason::fallthrough());
        self::assertEquals($detail, $result->getDetail());

        self::assertEquals(1, count($recorder->evals));
        $eval = $recorder->evals[0];
        self::assertEquals($flag1, $eval->getFlag());
        self::assertEquals('go', $eval->getResult()->getDetail()->getValue());
        self::assertEquals($flag0, $eval->getPrereqOfFlag());
    }

    public function prerequisiteCycleDepths()
    {
        return [[1], [2], [3], [4]];
    }

    /** @dataProvider prerequisiteCycleDepths */
    public function testPrerequisiteCycleDetection($depth)
    {
        $flagKeys = [];
        for ($i = 0; $i < $depth; $i++) {
            $flagKeys[] = "flagkey$i";
        }
        $flags = [];
        $requester = new MockFeatureRequester();
        for ($i = 0; $i < $depth; $i++) {
            $flag = ModelBuilders::flagBuilder($flagKeys[$i])
                ->on(true)
                ->variations(false, true)
                ->offVariation(0)
                ->prerequisite($flagKeys[($i + 1) % $depth], 0)
                ->build();
            $flags[] = $flag;
            $requester->addFlag($flag);
        }
        $evaluator = new Evaluator($requester);

        $result = $evaluator->evaluate($flags[0], LDContext::create('user'), EvaluatorTestUtil::expectNoPrerequisiteEvals());
        // Note, we specified expectNoPrerequisiteEvals() above because we do not expect the evaluator
        // to *finish* evaluating any of these prerequisites (it can't, because of the cycle), and so
        // it won't get as far as emitting any prereq evaluation results.

        self::assertEquals(EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR), $result->getDetail()->getReason());
    }
}
