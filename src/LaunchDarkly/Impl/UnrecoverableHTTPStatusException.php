<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl;

/**
 * Used internally.
 *
 * @ignore
 * @internal
 */
class UnrecoverableHTTPStatusException extends \Exception
{
    public int $status;

    public function __construct(int $status)
    {
        $this->status = $status;
    }
}
