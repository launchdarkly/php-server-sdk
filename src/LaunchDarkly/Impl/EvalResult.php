<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl;

use LaunchDarkly\EvaluationDetail;

/**
 * Internal class that holds intermediate flag evaluation results.
 *
 * @ignore
 * @internal
 */
class EvalResult
{
    private EvaluationDetail $_detail;
    private array $_prerequisiteEvents = [];

    public function __construct(EvaluationDetail $detail, array $prerequisiteEvents)
    {
        $this->_detail = $detail;
        $this->_prerequisiteEvents = $prerequisiteEvents;
    }

    public function getDetail(): EvaluationDetail
    {
        return $this->_detail;
    }

    public function getPrerequisiteEvents(): array
    {
        return $this->_prerequisiteEvents;
    }
}
