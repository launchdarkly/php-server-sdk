<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

/**
 * Metadata about a hook implementation.
 */
final class Metadata
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
