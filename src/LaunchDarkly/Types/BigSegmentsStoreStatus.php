<?php

declare(strict_types=1);

namespace LaunchDarkly\Types;

/**
 * Information about the status of a Big Segment store, provided by {@see LaunchDarkly\BigSegmentsStoreStatusProvider}.
 *
 * Big Segments are a specific type of segments. For more information, read the LaunchDarkly
 * documentation: https://docs.launchdarkly.com/home/users/big-segments
 */
class BigSegmentsStoreStatus
{
    public function __construct(private bool $available, private bool $stale)
    {
    }

    /**
     * True if the Big Segment store is able to respond to queries, so that the
     * SDK can evaluate whether a context is in a segment or not.
     *
     * If this property is false, the store is not able to make queries (for
     * instance, it may not have a valid database connection). In this case,
     * the SDK will treat any reference to a Big Segment as if no contexts are
     * included in that segment. Also, the {@see LaunchDarkly\EvaluationReason}
     * associated with with any flag evaluation that references a Big Segment
     * when the store is not available will have a `big_segments_status` of
     * `STORE_ERROR`.
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * True if the Big Segment store is available, but has not been updated
     * within the amount of time specified by {@see
     * LaunchDarkly\Types\BigSegmentConfig::$staleAfter}.
     *
     * This may indicate that the LaunchDarkly Relay Proxy, which populates the
     * store, has stopped running or has become unable to receive fresh data
     * from LaunchDarkly. Any feature flag evaluations that reference a Big
     * Segment will be using the last known data, which may be out of date.
     * Also, the {@see LaunchDarkly\EvaluationReason} associated with those
     * evaluations will have a `big_segments_status` of `STALE`.
     */
    public function isStale(): bool
    {
        return $this->stale;
    }
}
