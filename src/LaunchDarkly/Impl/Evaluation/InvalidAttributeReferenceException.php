<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\EvaluationReason;

/**
 * @internal
 * @ignore
 */
class InvalidAttributeReferenceException extends EvaluationException
{
    public function __construct(string $message)
    {
        parent::__construct($message, EvaluationReason::MALFORMED_FLAG_ERROR);
    }
}
