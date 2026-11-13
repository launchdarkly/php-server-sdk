<?php

declare(strict_types=1);

namespace LaunchDarkly\Hooks;

/**
 * Metadata about a hook implementation.
 *
 * @psalm-api
 */
final class Metadata
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
