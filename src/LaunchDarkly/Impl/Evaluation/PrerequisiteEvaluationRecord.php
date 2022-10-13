<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\Impl\Model\FeatureFlag;

/**
 * Holds information about an evaluation of a prerequisite flag that happened as a side
 * effect of evaluating another flag.
 *
 * @ignore
 * @internal
 */
class PrerequisiteEvaluationRecord
{
    private FeatureFlag $_flag;
    private FeatureFlag $_prereqOfFlag;
    private EvalResult $_result;

    public function __construct(FeatureFlag $flag, FeatureFlag $prereqOfFlag, EvalResult $result)
    {
        $this->_flag = $flag;
        $this->_prereqOfFlag = $prereqOfFlag;
        $this->_result = $result;
    }

    public function getFlag(): FeatureFlag
    {
        return $this->_flag;
    }

    public function getPrereqOfFlag(): FeatureFlag
    {
        return $this->_prereqOfFlag;
    }

    public function getResult(): EvalResult
    {
        return $this->_result;
    }
}
