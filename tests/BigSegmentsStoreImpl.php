<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests;

use LaunchDarkly\Subsystems\BigSegmentsStore;
use LaunchDarkly\Types\BigSegmentsStoreMetadata;

class BigSegmentsStoreImpl implements BigSegmentsStore
{
    /**
    * @param array<BigSegmentsStoreMetadata> $metadata
    * @param array<?array<string, bool>> $memberships
    */
    public function __construct(private array $metadata, private array $memberships)
    {
    }

    public function getMetadata(): BigSegmentsStoreMetadata
    {
        return array_shift($this->metadata);
    }

    public function getMembership(string $contextHash): ?array
    {
        return array_shift($this->memberships);
    }
}
