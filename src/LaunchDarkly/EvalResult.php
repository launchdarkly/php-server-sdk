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
    private $_detail = null;
    /** @var array */
    private $_prerequisiteEvents = [];

    /**
     * EvalResult constructor.
     * @param EvaluationDetail $detail
     * @param array $prerequisiteEvents
     */
    public function __construct($detail, array $prerequisiteEvents)
    {
        $this->_detail = $detail;
        $this->_prerequisiteEvents = $prerequisiteEvents;
    }

    /**
     * @return EvaluationDetail
     */
    public function getDetail()
    {
        return $this->_detail;
    }

    /**
     * @return array
     */
    public function getPrerequisiteEvents()
    {
        return $this->_prerequisiteEvents;
    }
}
