<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Migrations;

use Closure;
use Exception;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\Migrations\OperationResult;
use LaunchDarkly\Migrations\OpTracker;
use LaunchDarkly\Migrations\Origin;
use LaunchDarkly\Types\Result;

/**
 * Utility class for executing migration operations while also tracking our
 * built-in migration measurements.
 */
class Executor
{
    /**
     * @param Closure(mixed): Result $fn
     */
    public function __construct(
        public readonly Origin $origin,
        private Closure $fn,
        private OpTracker $tracker,
        private bool $trackLatency,
        private bool $trackErrors,
        private mixed $payload,
    ) {
    }

    public function run(): OperationResult
    {
        $start = Util::currentTimeUnixMillis();

        try {
            $result = ($this->fn)($this->payload);
        } catch (Exception $e) {
            $result = Result::error($e->getMessage(), $e);
        }

        if ($this->trackLatency) {
            $this->tracker->latency($this->origin, Util::currentTimeUnixMillis() - $start);
        }

        if ($this->trackErrors && !$result->isSuccessful()) {
            $this->tracker->error($this->origin);
        }

        $this->tracker->invoked($this->origin);

        return new OperationResult($this->origin, $result);
    }
}
