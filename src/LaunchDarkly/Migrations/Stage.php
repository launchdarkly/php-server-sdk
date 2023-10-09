<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * Stage denotes one of six possible stages a technology migration could be a
 * part of, progressing through the following order.
 *
 * OFF -> DUALWRITE -> SHADOW -> LIVE -> RAMPDOWN -> COMPLETE
 */
enum Stage: string
{
    /**
     * The migration hasn't started. 'old' is authoritative for reads and writes
     */
    case OFF = 'off';

    /**
     * Write to both 'old' and 'new', 'old' is authoritative for reads
     */
    case DUALWRITE = 'dualwrite';

    /**
     * Both 'new' and 'old' versions run with a preference for 'old'
    */
    case SHADOW = 'shadow';

    /**
     * Both 'new' and 'old' versions run with a preference for 'new'
    */
    case LIVE = 'live';

    /**
     * Only read from 'new', write to 'old' and 'new'
     */
    case RAMPDOWN = 'rampdown';

    /**
     * The migration is finished. 'new' is authoritative for reads and writes
     */
    case COMPLETE = 'complete';
}
