<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

/**
 * Used within the evaluation flow to short-circuit evaluation and force an error result.
 * This exception should never be thrown outside of the Evaluator into application code.
 *
 * @internal
 * @ignore
 */
class EvaluationException extends \Exception
{
    private string $_errorKind;

    public function __construct(string $message, string $errorKind)
    {
        parent::__construct($message);
        $this->_errorKind = $errorKind;
    }

    public function getErrorKind(): string
    {
        return $this->_errorKind;
    }
}
