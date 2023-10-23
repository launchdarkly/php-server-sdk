<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use Exception;
use LaunchDarkly\Types\Result;

/**
 * The OperationResult pairs an origin with a result.
 */
class OperationResult
{
    public readonly mixed $value;
    public readonly ?string $error;
    public readonly ?Exception $exception;

    public function __construct(
        public readonly Origin $origin,
        private readonly Result $result
    ) {
        $this->value = $result->value;
        $this->error = $result->error;
        $this->exception = $result->exception;
    }

    /**
     * Determine whether this result represents success or failure.
     */
    public function isSuccessful(): bool
    {
        return $this->result->isSuccessful();
    }
}
