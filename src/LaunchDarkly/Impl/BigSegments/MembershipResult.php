<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\BigSegments;

use LaunchDarkly\BigSegmentsEvaluationStatus;

class MembershipResult
{
    /**
     * @param ?array<string, bool> $membership A map from segment reference to
     *   inclusion status (true if the context is included, false if excluded).
     *   If null, the membership could not be retrieved.
     */
    public function __construct(
        public readonly ?array $membership,
        public readonly BigSegmentsEvaluationStatus $status
    ) {
    }
}
