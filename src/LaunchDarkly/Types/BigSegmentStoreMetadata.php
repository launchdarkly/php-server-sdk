<?php

declare(strict_types=1);

namespace LaunchDarkly\Types;

/**
 * Metadata about the state of a big segment store.
 */
class BigSegmentStoreMetadata
{
    public function __construct(private ?int $lastUpToDate)
    {
    }

    /**
     * The unix timestamp of the last update to the big segment store. It is
     * null if the store has never been updated.
     */
    public function getLastUpToDate(): ?int
    {
        return $this->lastUpToDate;
    }

    /**
    * Returns true if the metadata is considered stale, based on the current
    * time and the provided staleAfter seconds value. If the metadata has never
    * been updated, it is considered stale.
    */
    public function isStale(int $staleAfter): bool
    {
        if ($this->lastUpToDate === null) {
            return true;
        }

        return time() - $this->lastUpToDate >= $staleAfter;
    }
}
