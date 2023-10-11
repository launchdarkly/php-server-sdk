<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use LaunchDarkly\Types\Result;

/**
 * The OperationResult pairs an origin with a result.
 */
class OperationResult
{
    public function __construct(
        public readonly Origin $origin,
        public readonly Result $result
    ) {
    }
}
