<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

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
    private ?EvaluatorState $_state;
    private bool $_forceReasonTracking;

    /**
     * @param EvaluationDetail $detail
     * @param bool $forceReasonTracking
     */
    public function __construct(EvaluationDetail $detail, bool $forceReasonTracking = false, ?EvaluatorState $state = null)
    {
        $this->_detail = $detail;
        $this->_state = $state;
        $this->_forceReasonTracking = $forceReasonTracking;
    }

    public function withState(EvaluatorState $state): EvalResult
    {
        return new EvalResult($this->_detail, $this->_forceReasonTracking, $state);
    }

    public function withDetail(EvaluationDetail $detail): EvalResult
    {
        return new EvalResult($detail, $this->_forceReasonTracking, $this->_state);
    }

    public function getDetail(): EvaluationDetail
    {
        return $this->_detail;
    }

    public function getState(): ?EvaluatorState
    {
        return $this->_state;
    }

    public function isForceReasonTracking(): bool
    {
        return $this->_forceReasonTracking;
    }
}
