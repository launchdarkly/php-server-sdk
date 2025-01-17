<?php

namespace LaunchDarkly\Tests\Impl\Evaluation;

use LaunchDarkly\Impl\BigSegments\StoreManager;
use LaunchDarkly\Impl\Evaluation\Evaluator;
use LaunchDarkly\Impl\Evaluation\PrerequisiteEvaluationRecord;
use LaunchDarkly\Tests\MockFeatureRequester;
use LaunchDarkly\Types\BigSegmentsConfig;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class EvaluatorTestUtil
{
    public static function basicEvaluator(): Evaluator
    {
        return new Evaluator(
            new MockFeatureRequester(),
            new StoreManager(config: new BigSegmentsConfig(store: null), logger: self::testLogger()),
            self::testLogger()
        );
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

    public static function testLogger(): LoggerInterface
    {
        return new Logger("EvaluatorTest", [new ErrorLogHandler()]);
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
