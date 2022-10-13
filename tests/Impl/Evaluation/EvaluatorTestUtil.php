<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\PrerequisiteEvaluationRecord;
use LaunchDarkly\Tests\MockFeatureRequester;

class EvaluatorTestUtil
{
    public static function basicEvaluator(): Evaluator
    {
        return new Evaluator(new MockFeatureRequester());
    }

    public static function expectNoPrerequisiteEvals(): callable
    {
        return function (PrerequisiteEvaluationRecord $pe) {
            throw new \Exception('Test did not expect to receive any prerequisite evaluations, but got: ' .
                print_r($pe, true));
        };
    }

    public static function prerequisiteRecorder(): PrerequisiteRecorder
    {
        return new PrerequisiteRecorder();
    }
}

class PrerequisiteRecorder
{
    public array $evals = [];

    public function record(): callable
    {
        return function (PrerequisiteEvaluationRecord $pe) {
            $this->evals[] = $pe;
        };
    }
}
