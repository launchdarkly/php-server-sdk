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
    /** @var int */
    public $status;

    public function __construct(int $status)
    {
        $this->status = $status;
    }
}
