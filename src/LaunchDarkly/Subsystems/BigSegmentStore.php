<?php

declare(strict_types=1);

namespace LaunchDarkly\Subsystems;

use LaunchDarkly\Types;

/**
 * Interface for a store that provides access to Big Segments data.
 */
interface BigSegmentStore
{
    /**
     * Returns information about the overall state of the store. This method
     * will be called only when the SDK needs the latest state, so it should
     * not be cached.
     */
    public function getMetadata(): Types\BigSegmentStoreMetadata;

    /**
     * Queries the store for a snapshot of the current segment state for a
     * specific context.
     *
     * The $contextHash is a base64-encoded string produced by hashing the
     * context key as defined by the Big Segments specification; the store
     * implementation does not need to know the details of how this is done,
     * because it deals only with already-hashed keys, but the string can be
     * assumed to only contain characters that are valid in base64.
     *
     * The return value should be either a array, or nil if the context is not
     * referenced in any big segments. Each key in the array is a "segment
     * reference", which is how segments are identified in Big Segment data.
     *
     * This string is not identical to the segment key-- the SDK will add other
     * information. The store implementation should not be concerned with the
     * format of the string. Each value in the array is true if the context is
     * explicitly included in the segment, false if the context is explicitly
     * excluded from the segment-- and is not also explicitly included (that
     * is, if both an include and an exclude existed in the data, the include
     * would take precedence). If the context's status in a particular segment
     * is undefined, there should be no key or value for that segment.
     *
     * This array may be cached by the SDK, so it should not be modified after
     * it is created. It is a snapshot of the segment membership state at one
     * point in time.
     *
     * @return array<string, bool>|null A map from segment reference to inclusion status
     */
    public function getMembership(string $contextHash): ?array;
}
