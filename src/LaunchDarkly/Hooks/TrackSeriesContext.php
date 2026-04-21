<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

use LaunchDarkly\LDContext;

/**
 * Contextual information provided to the afterTrack stage of the track series.
 */
final class TrackSeriesContext
{
    public function __construct(
        public readonly LDContext $context,
        public readonly string $key,
        public readonly int|float|null $metricValue,
        public readonly mixed $data,
    ) {
    }
}
