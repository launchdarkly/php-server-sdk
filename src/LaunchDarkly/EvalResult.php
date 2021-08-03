<?php

namespace LaunchDarkly;

/**
 * Internal class that holds intermediate flag evaluation results.
 *
 * @ignore
 * @internal
 */
class EvalResult
{
    /** @var EvaluationDetail */
    private $_detail;
    /** @var array */
    private $_prerequisiteEvents = [];

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
