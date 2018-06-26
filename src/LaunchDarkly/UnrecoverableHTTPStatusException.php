<?php
namespace LaunchDarkly;

/**
 * Used internally.
 */
class UnrecoverableHTTPStatusException extends \Exception
{
    public $status;

    public function __construct($status)
    {
        $this->status = $status;
    }
}
