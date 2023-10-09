<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * The operation enum is used to record the type of migration operation that
 * occurred.
 */
enum Operation: string
{
    /**
     * READ represents a read-only operation on an origin of data.
     *
     * A read operation carries the implication that it can be executed in
     * parallel against multiple origins.
     */
    case READ = 'read';

    /**
     * WRITE represents a write operation on an origin of data.
     *
     * A write operation implies that execution cannot be done in parallel
     * against multiple origins.
     */
    case WRITE = 'write';
}
