<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\LDContext;
use LaunchDarkly\Tests\FlagBuilder;
use LaunchDarkly\Tests\ModelBuilders;
use PHPUnit\Framework\TestCase;

class EvaluatorTargetTest extends TestCase
{
    const FALLTHROUGH_VAR = 0, MATCH_VAR_1 = 1, MATCH_VAR_2 = 2;
    const VARIATIONS = ['fallthrough', 'match1', 'match2'];

    private static Evaluator $basicEvaluator;

    public static function setUpBeforeClass(): void
    {
        static::$basicEvaluator = EvaluatorTestUtil::basicEvaluator();
    }

    public function testUserTargetsOnly()
    {
        $f = self::baseFlagBuilder()
            ->target(self::MATCH_VAR_1, 'c')
            ->target(self::MATCH_VAR_2, 'b', 'a')
            ->build();
        
        self::expectMatch($f, LDContext::create('a'), self::MATCH_VAR_2);
        self::expectMatch($f, LDContext::create('b'), self::MATCH_VAR_2);
        self::expectMatch($f, LDContext::create('c'), self::MATCH_VAR_1);
        self::expectFallthrough($f, LDContext::create('z'));

        // in a multi-kind context, these targets match only the key for the user kind
        self::expectMatch(
            $f,
            LDContext::createMulti(LDContext::create('b', 'dog'), LDContext::create('a')),
            self::MATCH_VAR_2
        );
        self::expectMatch(
            $f,
            LDContext::createMulti(LDContext::create('a', 'dog'), LDContext::create('c')),
            self::MATCH_VAR_1
        );
        self::expectFallthrough(
            $f,
            LDContext::createMulti(LDContext::create('b', 'dog'), LDContext::create('z'))
        );
        self::expectFallthrough(
            $f,
            LDContext::createMulti(LDContext::create('a', 'dog'), LDContext::create('b', 'cat'))
        );
    }

    public function userTargetsAndContextTargets()
    {
        $f = self::baseFlagBuilder()
            ->target(self::MATCH_VAR_1, 'c')
            ->target(self::MATCH_VAR_2, 'b', 'a')
            ->contextTarget('dog', self::MATCH_VAR_1, 'a', 'b')
            ->contextTarget('dog', self::MATCH_VAR_2, 'c')
            ->contextTarget(LDContext::DEFAULT_KIND, self::MATCH_VAR_1)
            ->contextTarget(LDContext::DEFAULT_KIND, self::MATCH_VAR_2)
            ->build();

        self::expectMatch($f, LDContext::create('a'), self::MATCH_VAR_2);
        self::expectMatch($f, LDContext::create('b'), self::MATCH_VAR_2);
        self::expectMatch($f, LDContext::create('c'), self::MATCH_VAR_1);
        self::expectFallthrough($f, LDContext::create('z'));
    
        self::expectMatch(
            $f,
            LDContext::createMulti(LDContext::create('b', 'dog'), LDContext::create('a')),
            self::MATCH_VAR_1 // the "dog" target takes precedence due to ordering
        );
        self::expectMatch(
            $f,
            LDContext::createMulti(LDContext::create('z', 'dog'), LDContext::create('a')),
            self::MATCH_VAR_2 // "dog" targets don't match, continue to "user" targets
        );
        self::expectFallthrough(
            $f,
            LDContext::createMulti(LDContext::create('x', 'dog'), LDContext::create('z')) // nothing matches
        );
        self::expectMatch(
            $f,
            LDContext::createMulti(LDContext::create('a', 'dog'), LDContext::create('b', 'cat')),
            self::MATCH_VAR_1
        );
    }
  
    private static function baseFlagBuilder(): FlagBuilder
    {
        return ModelBuilders::flagBuilder('feature')->on(true)->variations(...self::VARIATIONS)
            ->fallthroughVariation(self::FALLTHROUGH_VAR)->offVariation(self::FALLTHROUGH_VAR);
    }

    private function expectMatch(FeatureFlag $f, LDContext $c, int $v)
    {
        $result = EvaluatorTestUtil::basicEvaluator()->evaluate($f, $c, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        self::assertEquals($v, $result->getDetail()->getVariationIndex());
        self::assertEquals(self::VARIATIONS[$v], $result->getDetail()->getValue());
        self::assertEquals(EvaluationReason::targetMatch(), $result->getDetail()->getReason());
    }

    private function expectFallthrough(FeatureFlag $f, LDContext $c)
    {
        $result = EvaluatorTestUtil::basicEvaluator()->evaluate($f, $c, EvaluatorTestUtil::expectNoPrerequisiteEvals());
        self::assertEquals(self::FALLTHROUGH_VAR, $result->getDetail()->getVariationIndex());
        self::assertEquals(self::VARIATIONS[self::FALLTHROUGH_VAR], $result->getDetail()->getValue());
        self::assertEquals(EvaluationReason::fallthrough(), $result->getDetail()->getReason());
    }
}
