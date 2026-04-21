<?php

declare(strict_types=1);

namespace LaunchDarkly\Tests\Hooks;

/**
 * Mutable shared log used by tests to observe the order in which multiple hooks are invoked.
 * Wraps an array so callers can share a single instance across hooks.
 */
final class CallLog
{
    /** @var array<int, array<string, mixed>> */
    public array $calls = [];

    /**
     * @param array<string, mixed> $entry
     */
    public function append(array $entry): void
    {
        $this->calls[] = $entry;
    }
}
