<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * The WriteResult pairs an origin with a result.
 */
class WriteResult
{
    public function __construct(
        public readonly OperationResult $authoritative,
        public readonly ?OperationResult $nonauthoritative = null
    ) {
    }
}
