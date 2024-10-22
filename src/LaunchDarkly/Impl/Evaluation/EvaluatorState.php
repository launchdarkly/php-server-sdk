<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\Impl\Model\FeatureFlag;

/**
 * @ignore
 * @internal
 */
class EvaluatorState
{
    public ?array $prerequisiteStack = null;
    public ?array $segmentStack = null;
    public ?array $prerequisites = null;
    public int $depth = 0;

    public function __construct(public FeatureFlag $originalFlag)
    {
    }
}
