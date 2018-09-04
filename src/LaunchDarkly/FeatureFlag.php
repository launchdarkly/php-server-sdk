<?php
namespace LaunchDarkly;

class FeatureFlag
{
    protected static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    /** @var string */
    protected $_key = null;
    /** @var int */
    protected $_version = null;
    /** @var bool */
    protected $_on = false;
    /** @var Prerequisite[] */
    protected $_prerequisites = array();
    /** @var string */
    protected $_salt = null;
    /** @var Target[] */
    protected $_targets = array();
    /** @var Rule[] */
    protected $_rules = array();
    /** @var VariationOrRollout */
    protected $_fallthrough = null;
    /** @var int | null */
    protected $_offVariation = null;
    /** @var array */
    protected $_variations = array();
    /** @var bool */
    protected $_deleted = false;
    /** @var bool */
    protected $_trackEvents = false;
    /** @var int | null */
    protected $_debugEventsUntilDate = null;
    /** @var bool */
    protected $_clientSide = false;

    // Note, trackEvents and debugEventsUntilDate are not used in EventProcessor, because
    // the PHP client doesn't do summary events. However, we need to capture them in case
    // they want to pass the flag data to the front end with allFlagsState().

    protected function __construct($key,
                                   $version,
                                   $on,
                                   array $prerequisites,
                                   $salt,
                                   array $targets,
                                   array $rules,
                                   $fallthrough,
                                   $offVariation,
                                   array $variations,
                                   $deleted,
                                   $trackEvents,
                                   $debugEventsUntilDate,
                                   $clientSide)
    {
        $this->_key = $key;
        $this->_version = $version;
        $this->_on = $on;
        $this->_prerequisites = $prerequisites;
        $this->_salt = $salt;
        $this->_targets = $targets;
        $this->_rules = $rules;
        $this->_fallthrough = $fallthrough;
        $this->_offVariation = $offVariation;
        $this->_variations = $variations;
        $this->_deleted = $deleted;
        $this->_trackEvents = $trackEvents;
        $this->_debugEventsUntilDate = $debugEventsUntilDate;
        $this->_clientSide = $clientSide;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new FeatureFlag(
                $v['key'],
                $v['version'],
                $v['on'],
                array_map(Prerequisite::getDecoder(), $v['prerequisites'] ?: []),
                $v['salt'],
                array_map(Target::getDecoder(), $v['targets'] ?: []),
                array_map(Rule::getDecoder(), $v['rules'] ?: []),
                call_user_func(VariationOrRollout::getDecoder(), $v['fallthrough']),
                $v['offVariation'],
                $v['variations'] ?: [],
                $v['deleted'],
                isset($v['trackEvents']) && $v['trackEvents'],
                isset($v['debugEventsUntilDate']) ? $v['debugEventsUntilDate'] : null,
                isset($v['clientSide']) && $v['clientSide']
            );
        };
    }

    public static function decode($v)
    {
        return call_user_func(FeatureFlag::getDecoder(), $v);
    }

    public function isOn()
    {
        return $this->_on;
    }

    /**
     * @param LDUser $user
     * @param FeatureRequester $featureRequester
     * @param bool $includeReasonsInEvents
     * @return EvalResult
     */
    public function evaluate($user, $featureRequester, $includeReasonsInEvents = false)
    {
        $prereqEvents = array();
        $detail = $this->evaluateInternal($user, $featureRequester, $prereqEvents, $includeReasonsInEvents);
        return new EvalResult($detail, $prereqEvents);
    }

    /**
     * @param LDUser $user
     * @param FeatureRequester $featureRequester
     * @param array $events
     * @param bool $includeReasonsInEvents
     * @return EvaluationDetail
     */
    private function evaluateInternal($user, $featureRequester, &$events, $includeReasonsInEvents)
    {
        if (!$this->isOn()) {
            return $this->getOffValue(EvaluationReason::off());
        }

        $prereqFailureReason = $this->checkPrerequisites($user, $featureRequester, $events, $includeReasonsInEvents);
        if ($prereqFailureReason !== null) {
            return $this->getOffValue($prereqFailureReason);
        }

        // Check to see if targets match
        if ($this->_targets != null) {
            foreach ($this->_targets as $target) {
                foreach ($target->getValues() as $value) {
                    if ($value === $user->getKey()) {
                        return $this->getVariation($target->getVariation(), EvaluationReason::targetMatch());
                    }
                }
            }
        }
        // Now walk through the rules and see if any match
        if ($this->_rules != null) {
            foreach ($this->_rules as $i => $rule) {
                if ($rule->matchesUser($user, $featureRequester)) {
                    return $this->getValueForVariationOrRollout($rule, $user,
                        EvaluationReason::ruleMatch($i, $rule->getId()));
                }
            }
        }
        return $this->getValueForVariationOrRollout($this->_fallthrough, $user, EvaluationReason::fallthrough());
    }

    /**
     * @param LDUser $user
     * @param FeatureRequester $featureRequester
     * @param array $events
     * @param bool $includeReasonsInEvents
     * @return EvaluationReason|null
     */
    private function checkPrerequisites($user, $featureRequester, &$events, $includeReasonsInEvents)
    {
        if ($this->_prerequisites != null) {
            foreach ($this->_prerequisites as $prereq) {
                $prereqOk = true;
                try {
                    $prereqEvalResult = null;
                    $prereqFeatureFlag = $featureRequester->getFeature($prereq->getKey());
                    if ($prereqFeatureFlag == null) {
                        $prereqOk = false;
                    } else {
                        $prereqEvalResult = $prereqFeatureFlag->evaluateInternal($user, $featureRequester, $events, $includeReasonsInEvents);
                        $variation = $prereq->getVariation();
                        if (!$prereqFeatureFlag->isOn() || $prereqEvalResult->getVariationIndex() !== $variation) {
                            $prereqOk = false;
                        }
                        array_push($events, Util::newFeatureRequestEvent($prereq->getKey(), $user,
                            $prereqEvalResult->getVariationIndex(), $prereqEvalResult->getValue(),
                            null, $prereqFeatureFlag->getVersion(), $this->_key,
                            ($includeReasonsInEvents && $prereqEvalResult) ? $prereqEvalResult->getReason() : null
                        ));
                    }
                } catch (EvaluationException $e) {
                    $prereqOk = false;
                }
                if (!$prereqOk) {
                    return EvaluationReason::prerequisiteFailed($prereq->getKey());
                }
            }
        }
        return null;
    }

    /**
     * @param int $index
     * @param EvaluationReason $reason
     * @return EvaluationDetail
     */
    private function getVariation($index, $reason)
    {
        if ($index < 0 || $index >= count($this->_variations)) {
            return new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        }
        return new EvaluationDetail($this->_variations[$index], $index, $reason);
    }

    /**
     * @param EvaluationReason reason
     * @return EvaluationDetail
     */
    private function getOffValue($reason)
    {
        if ($this->_offVariation === null) {
            return new EvaluationDetail(null, null, $reason);
        }
        return $this->getVariation($this->_offVariation, $reason);
    }
    
    /**
     * @param VariationOrRollout $r
     * @param LDUser $user
     * @param EvaluationReason $reason
     * @return EvaluationDetail
     */
    private function getValueForVariationOrRollout($r, $user, $reason)
    {
        $index = $r->variationIndexForUser($user, $this->_key, $this->_salt);
        if ($index === null) {
            return new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        }
        return $this->getVariation($index, $reason);
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->_deleted;
    }

    /**
     * @return boolean
     */
    public function isTrackEvents()
    {
        return $this->_trackEvents;
    }

    /**
     * @return int | null
     */
    public function getDebugEventsUntilDate()
    {
        return $this->_debugEventsUntilDate;
    }

    /**
     * @return boolean
     */
    public function isClientSide()
    {
        return $this->_clientSide;
    }
}
