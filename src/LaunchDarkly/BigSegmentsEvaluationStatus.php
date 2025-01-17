<?php

declare(strict_types=1);

namespace LaunchDarkly;

/**
 * A status enum which represents the result of a Big Segment query involved in
 * a flag evaluation.
 */
enum BigSegmentsEvaluationStatus: string
{
    /**
     * Indicates that the Big Segment query involved in the flag evaluation was
     * successful, and that the segment state is considered up to date.
     */
    case HEALTHY = 'HEALTHY';

    /**
     * Indicates that the Big Segment query involved in the flag evaluation was
     * successful, but that the segment state may not be up to date.
     */
    case STALE = 'STALE';

    /**
     * Indicates that Big Segments could not be queried for the flag evaluation
     * because the SDK configuration did not include a Big Segment store.
     */
    case NOT_CONFIGURED = 'NOT_CONFIGURED';

    /**
     * Indicates that the Big Segment query involved in the flag evaluation
     * failed, for instance due to a database error.
     */
    case STORE_ERROR = 'STORE_ERROR';
}
