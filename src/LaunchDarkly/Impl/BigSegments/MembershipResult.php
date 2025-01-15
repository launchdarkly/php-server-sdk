<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\BigSegments;

use LaunchDarkly\BigSegmentsEvaluationStatus;

class MembershipResult
{
    /**
    * @param ?array<string, bool> $membership
    */
    public function __construct(
        public readonly ?array $membership,
        public readonly BigSegmentsEvaluationStatus $status
    ) {
    }
}
