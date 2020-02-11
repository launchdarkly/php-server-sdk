<?php
namespace LaunchDarkly;

/**
 * Used internally.
 *
 * @ignore
 * @internal
 */
class UnrecoverableHTTPStatusException extends \Exception
{
    public $status;

    public function __construct($status)
    {
        $this->status = $status;
    }
}
