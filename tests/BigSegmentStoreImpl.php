<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests;

use LaunchDarkly\Subsystems\BigSegmentStore;
use LaunchDarkly\Types\BigSegmentStoreMetadata;

class BigSegmentStoreImpl implements BigSegmentStore
{
    /**
    * @param array<BigSegmentStoreMetadata> $metadata
    * @param array<?array<string, bool>> $memberships
    */
    public function __construct(private array $metadata, private array $memberships)
    {
    }

    public function getMetadata(): BigSegmentStoreMetadata
    {
        return array_shift($this->metadata);
    }

    public function getMembership(string $contextHash): ?array
    {
        return array_shift($this->memberships);
    }
}
