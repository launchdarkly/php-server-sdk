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
        // Do not chain the Guzzle exception. Its request has the SDK key.
        parent::__construct("HTTP error $status", $status);
        $this->status = $status;
    }
}
