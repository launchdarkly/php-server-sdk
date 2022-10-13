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
    private bool $_forceReasonTracking;

    /**
     * @param EvaluationDetail $detail
     * @param bool $forceReasonTracking
     */
    public function __construct(EvaluationDetail $detail, bool $forceReasonTracking = false)
    {
        $this->_detail = $detail;
        $this->_forceReasonTracking = $forceReasonTracking;
    }

    public function getDetail(): EvaluationDetail
    {
        return $this->_detail;
    }

    public function isForceReasonTracking(): bool
    {
        return $this->_forceReasonTracking;
    }
}
