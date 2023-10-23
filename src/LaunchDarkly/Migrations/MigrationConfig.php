<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

use Closure;
use LaunchDarkly\Types\Result;

/**
 * A migration config stores references to callable methods which execute
 * customer defined read or write operations on old or new origins of
 * information. For read operations, an optional comparison function also be
 * defined.
 */
class MigrationConfig
{
    /**
     * @param Closure(mixed): Result $old
     * @param Closure(mixed): Result $new
     * @param ?Closure(mixed, mixed): bool $comparison
     */
    public function __construct(
        public readonly Closure $old,
        public readonly Closure $new,
        public readonly ?Closure $comparison = null
    ) {
    }
}
