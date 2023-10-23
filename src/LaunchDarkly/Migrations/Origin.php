<?php

declare(strict_types=1);

namespace LaunchDarkly\Migrations;

/**
 * The origin enum is used to denote which source of data should be affected
 * by a particular operation.
 */
enum Origin: string
{
    /**
     * The OLD origin is the source of data we are migrating from. When the
     * migration is complete, this source of data will be unused.
     */
    case OLD = 'old';

    /**
     * The NEW origin is the source of data we are migrating to. When the
     * migration is complete, this source of data will be the source of
     * truth.
     */
    case NEW = 'new';
}
