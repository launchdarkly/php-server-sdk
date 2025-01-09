<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\BigSegmentEvaluationStatus;
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
    public ?BigSegmentEvaluationStatus $bigSegmentEvaluationStatus = null;

    /**
    * An associative array, indexed by an LDContext's key. Each value is a
    * boolean indicating whether the user is a member of the corresponding
    * segment.
    *
    * If the value is null, no big segment was referenced for this evaluation.
    *
    * @var ?array<string, ?array<string, bool>>
    */
    public ?array $bigSegmentMembership = null;

    public function __construct(public FeatureFlag $originalFlag)
    {
    }
}
