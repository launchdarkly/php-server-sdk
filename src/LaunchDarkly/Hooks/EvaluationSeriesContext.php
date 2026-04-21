<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

use LaunchDarkly\LDContext;

/**
 * Contextual information provided to stages of the evaluation series.
 *
 * An instance is created once per variation call and passed, unchanged, to every
 * stage of every registered hook for that call.
 */
final class EvaluationSeriesContext
{
    public function __construct(
        public readonly string $flagKey,
        public readonly LDContext $context,
        public readonly mixed $defaultValue,
        public readonly string $method,
    ) {
    }
}
