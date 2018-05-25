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
                                   $deleted)
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
                $v['deleted']);
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
     * @param $user LDUser
     * @param $featureRequester FeatureRequester
     * @return EvalResult|null
     */
    public function evaluate($user, $featureRequester)
    {
        $prereqEvents = array();
        if (is_null($user) || is_null($user->getKey())) {
            return new EvalResult(null, $prereqEvents);
        }
        if ($this->isOn()) {
            $result = $this->_evaluate($user, $featureRequester, $prereqEvents);
            if ($result !== null) {
                return $result;
            }
        }
        $offVariationValue = $this->getOffVariationValue();
        return new EvalResult($this->_offVariation, $offVariationValue, $prereqEvents);
    }

    /**
     * @param $user LDUser
     * @param $featureRequester FeatureRequester
     * @param $events
     * @return EvalResult|null
     */
    private function _evaluate($user, $featureRequester, &$events)
    {
        $prereqOk = true;
        if ($this->_prerequisites != null) {
            foreach ($this->_prerequisites as $prereq) {
                try {
                    $prereqEvalResult = null;
                    $prereqFeatureFlag = $featureRequester->getFeature($prereq->getKey());
                    if ($prereqFeatureFlag == null) {
                        return null;
                    } elseif ($prereqFeatureFlag->isOn()) {
                        $prereqEvalResult = $prereqFeatureFlag->_evaluate($user, $featureRequester, $events);
                        $variation = $prereq->getVariation();
                        if ($prereqEvalResult === null || $variation === null || $prereqEvalResult->getVariation() !== $variation) {
                            $prereqOk = false;
                        }
                    } else {
                        $prereqOk = false;
                    }
                    array_push($events, Util::newFeatureRequestEvent($prereqFeatureFlag->getKey(), $user,
                        $prereqEvalResult === null ? null : $prereqEvalResult->getVariation(),
                        $prereqEvalResult === null ? null : $prereqEvalResult->getValue(),
                        null, $prereqFeatureFlag->getVersion(), $this->_key));
                } catch (EvaluationException $e) {
                    $prereqOk = false;
                }
            }
        }
        if ($prereqOk) {
            $variation = $this->evaluateIndex($user, $featureRequester);
            $value = $this->getVariation($variation);
            return new EvalResult($variation, $value, $events);
        }
        return null;
    }

    /**
     * @param $user LDUser
     * @return int|null
     */
    private function evaluateIndex($user, $featureRequester)
    {
        // Check to see if targets match
        if ($this->_targets != null) {
            foreach ($this->_targets as $target) {
                foreach ($target->getValues() as $value) {
                    if ($value === $user->getKey()) {
                        return $target->getVariation();
                    }
                }
            }
        }
        // Now walk through the rules and see if any match
        if ($this->_rules != null) {
            foreach ($this->_rules as $rule) {
                if ($rule->matchesUser($user, $featureRequester)) {
                    return $rule->variationIndexForUser($user, $this->_key, $this->_salt);
                }
            }
        }
        // Walk through the fallthrough and see if it matches
        return $this->_fallthrough->variationIndexForUser($user, $this->_key, $this->_salt);
    }

    private function getVariation($index)
    {
        // If the supplied index is null, then rules didn't match, and we want to return
        // the off variation
        if (!isset($index)) {
            return null;
        }
        // If the index doesn't refer to a valid variation, that's an unexpected exception and we will
        // return the default variation
        if ($index >= count($this->_variations)) {
            throw new EvaluationException("Invalid Index");
        } else {
            return $this->_variations[$index];
        }
    }

    public function getOffVariationValue()
    {
        if ($this->_offVariation === null) {
            return null;
        }
        if ($this->_offVariation >= count($this->_variations)) {
            throw new EvaluationException("Invalid offVariation index");
        }
        return $this->_variations[$this->_offVariation];
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
}
