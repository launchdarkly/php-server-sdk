<?php
namespace LaunchDarkly;

use Exception;

/**
 * Obsolete internal exception class, no longer used. Will be removed in a future version.
 *
 * @deprecated
 * @ignore
 * @internal
 */
class EvaluationException extends Exception
{
    /**
     * EvaluationException constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
