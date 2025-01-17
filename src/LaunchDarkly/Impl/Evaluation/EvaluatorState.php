<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\BigSegmentsEvaluationStatus;
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
    public ?BigSegmentsEvaluationStatus $bigSegmentsEvaluationStatus = null;

    /**
    * An associative array, indexed by an LDContext's key. Each value is a
    * boolean indicating whether the user is a member of the corresponding
    * segment.
    *
    * If the value is null, no big segments was referenced for this evaluation.
    *
    * @var ?array<string, ?array<string, bool>>
    */
    public ?array $bigSegmentsMembership = null;

    public function __construct(public FeatureFlag $originalFlag)
    {
    }
}
