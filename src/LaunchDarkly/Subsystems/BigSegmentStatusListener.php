<?php

declare(strict_types=1);

namespace LaunchDarkly\Subsystems;

use LaunchDarkly\Types;

/**
 * Interface for a listener that will be notified when the status of the Big Segment store changes.
 */
interface BigSegmentStatusListener
{
    /**
     * Called when the status of the Big Segment store has changed.
     *
     * The first time this is called, the old status will be `null` and the new status will be the
     * initial status of the store. After that, the old status will be the previous status and the
     * new status will be the current status.
     */
    public function statusChanged(
        ?Types\BigSegmentsStoreStatus $old,
        Types\BigSegmentsStoreStatus $new
    ): void;
}
