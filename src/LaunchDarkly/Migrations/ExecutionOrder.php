<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * Depending on the migration stage, reads may operate against both old and new
 * origins. In this situation, the execution order can be defined to specify
 * how these individual reads are coordinated.
 */
enum ExecutionOrder: string
{
    /**
     * SERIAL execution order ensures that the authoritative read completes
     * before the non-authoritative read is executed.
     */
    case SERIAL = 'serial';

    /**
     * Like SERIAL, RANDOM ensures that one read is completed before the
     * subsequent read is executed. However, the order in which they are
     * executed is randomly decided.
     */
    case RANDOM = 'random';
}
