<?php
namespace LaunchDarkly;

class FeatureFlag {
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
    protected $_offVariation = null;
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
                                   $deleted) {
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

    public static function decode($v) {
        return new FeatureFlag(
            $v['key'],
            $v['version'],
            $v['on'],
            array_map(Prerequisite::getDecoder(), $v['prerequisites']),
            $v['salt'],
            array_map(Target::getDecoder(), $v['targets']),
            array_map(Rule::getDecoder(), $v['rules']),
            call_user_func(VariationOrRollout::getDecoder(), $v['fallthrough']),
            $v['offVariation'],
            $v['variations'],
            $v['deleted']);
    }

    public function isOn() {
        return $this->_on;
    }

    /**
     * @param $user LDUser
     * @param $featureRequester FeatureRequester
     * @return mixed|null
     */
    public function evaluate($user, $featureRequester) {
        $prereqEvents = array();
        $value = $this->_evaluate($user, $featureRequester, $prereqEvents);
        return $value;
    }

    /**
     * @param $user LDUser
     * @param $featureRequester FeatureRequester
     * @param $events
     * @return mixed|null
     */
    private function _evaluate($user, $featureRequester, $events) {
        $prereqOk = true;
        if ($this->_prerequisites != null) {
            foreach ($this->_prerequisites as $prereq) {
                try {
                    $prereqFeatureFlag = $featureRequester->get($prereq->getKey());
                    if ($prereqFeatureFlag == null) {
                        return null;
                    } else if ($prereqFeatureFlag->isOn()) {
                        $prereqEvalResult = $prereqFeatureFlag->evaluate($user, $featureRequester);
                        $variation = $prereqFeatureFlag->getVariation($prereq->getVariation());
                        if ($prereqEvalResult == null || $variation == null || $prereqEvalResult != $variation) {
                            $prereqOk = false;
                        }
                    } else {
                        $prereqOk = false;
                    }
                } catch (EvaluationException $e) {
                    $prereqOk = false;
                }
                //TODO: Add event.
            }
        }
        if ($prereqOk) {
            return $this->getVariation($this->evaluateIndex($user));
        }
    }

    /**
     * @param $user LDUser
     * @return int|null
     */
    private function evaluateIndex($user) {
        // Check to see if targets match
        if ($this->_targets != null) {
            foreach ($this->_targets as $target) {
                foreach ($target->getValues() as $value) {
                    if ($value == $user->getKey()) {
                        return $target->getVariation();
                    }
                }
            }
        }
        // Now walk through the rules and see if any match
        if ($this->_rules != null) {
            foreach ($this->_rules as $rule) {
                if ($rule->matchesUser($user)) {
                    return $rule->variationIndexForUser($user, $this->_key, $this->_salt);
                }
            }
        }
        // Walk through the fallthrough and see if it matches
        return $this->_fallthrough->variationIndexForUser($user, $this->_key, $this->_salt);

    }

    private function getVariation($index) {
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

    public function getOffVariationValue() {
        if ($this->_offVariation == null) {
            return null;
        }
        if ($this->_offVariation >= count($this->_variations)) {
            throw new EvaluationException("Invalid offVariation index");
        }
        return $this->_variations[$this->_offVariation];
    }
}

class VariationOrRollout {
    private static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    /** @var int */
    private $_variation = null;
    /** @var Rollout */
    private $_rollout = null;

    protected function __construct($variation, $rollout) {
        $this->_variation = $variation;
        $this->_rollout = $rollout;
    }

    public static function getDecoder() {
        return function ($v) {
            return new VariationOrRollout(
                isset($v['variation']) ? $v['variation'] : null,
                isset($v['rollout']) ? $v['rollout'] : null);
        };
    }

    /**
     * @return int
     */
    public function getVariation() {
        return $this->_variation;
    }

    /**
     * @return Rollout
     */
    public function getRollout() {
        return $this->_rollout;
    }

    /**
     * @param $user LDUser
     * @param $_key string
     * @param $_salt string
     * @return int|null
     */
    public function variationIndexForUser($user, $_key, $_salt) {
        if ($this->_variation != null) {
            return $this->_variation;
        } else if ($this->_rollout != null) {
            $bucketBy = $this->_rollout->getBucketBy() == null ? "key" : $this->_rollout->getBucketBy();
            $bucket = $this->bucketUser($user, $_key, $bucketBy, $_salt);
            $sum = 0.0;
            foreach ($this->_rollout->getVariations() as $wv) {
                $sum += $wv->getWeight() / 100000.0;
                if ($bucket < $sum) {
                    return $wv->getVariation();
                }
            }
        }
        return null;
    }

    /**
     * @param $user LDUser
     * @param $_key string
     * @param $attr string
     * @param $_salt string
     * @return float
     */
    private function bucketUser($user, $_key, $attr, $_salt) {
        $userValue = $user->getValueForEvaluation($attr);
        $idHash = null;
        if ($userValue != null) {
            if (is_string($userValue)) {
                $idHash = $userValue;
                if ($user->getSecondary() != null) {
                    $idHash = $idHash . "." . $user->getSecondary();
                }
                $hash = substr(sha1($_key . "." . $_salt . "." . $idHash), 0, 15);
                $longVal = base_convert($hash, 16, 10);
                $result = $longVal / self::$LONG_SCALE;

                return $result;
            }
        }
        return 0.0;
    }
}

class Clause {
    private $_attribute = null;
    private $_op = null;
    private $_values = array();
    private $_negate = false;

    private function __construct($attribute, $op, array $values, $negate) {
        $this->_attribute = $attribute;
        $this->_op = $op;
        $this->_values = $values;
        $this->_negate = $negate;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Clause($v['attribute'], $v['op'], $v['values'], $v['negate']);
        };
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchesUser($user) {
        $userValue = $user->getValueForEvaluation($this->_attribute);
        if ($userValue == null) {
            return false;
        }
        if (is_array($userValue)) {
            foreach ($userValue as $element) {
                if ($this->matchAny($userValue)) {
                    return $this->_maybeNegate(true);
                }
            }
            return $this->maybeNegate(false);
        } else {
            return $this->maybeNegate($this->matchAny($userValue));
        }
    }

    /**
     * @return null
     */
    public function getAttribute() {
        return $this->_attribute;
    }

    /**
     * @return null
     */
    public function getOp() {
        return $this->_op;
    }

    /**
     * @return array
     */
    public function getValues() {
        return $this->_values;
    }

    /**
     * @return boolean
     */
    public function isNegate() {
        return $this->_negate;
    }

    /**
     * @param $userValue
     * @return bool
     */
    private function matchAny($userValue) {
        foreach ($this->_values as $v) {
            if (Operators::apply($this->_op, $userValue, $v)) {
                return true;
            }
        }
        return false;
    }

    private function _maybeNegate($b) {
        if ($this->_negate) {
            return !$b;
        } else {
            return $b;
        }
    }
}

class Rule extends VariationOrRollout {
    /** @var Clause[] */
    private $_clauses = array();

    protected function __construct($variation, $rollout, array $clauses) {
        parent::__construct($variation, $rollout);
        $this->_clauses = $clauses;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Rule(
                isset($v['variation']) ? $v['variation'] : null,
                isset($v['rollout']) ? $v['rollout'] : null,
                array_map(Clause::getDecoder(), $v['clauses']));
        };
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchesUser($user) {
        foreach ($this->_clauses as $clause) {
            if (!$clause->matchesUser($user)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return Clause[]
     */
    public function getClauses() {
        return $this->_clauses;
    }
}

class WeightedVariation {
    /** @var int */
    private $_variation = null;
    /** @var int */
    private $_weight = null;

    private function __construct($variation, $weight) {
        $this->_variation = $variation;
        $this->_weight = $weight;
    }

    public static function getDecoder() {
        return function ($v) {
            return new WeightedVariation($v['variation'], $v['weight']);
        };
    }

    /**
     * @return int
     */
    public function getVariation() {
        return $this->_variation;
    }

    /**
     * @return int
     */
    public function getWeight() {
        return $this->_weight;
    }
}

class Target {
    /** @var string[] */
    private $_values = array();
    /** @var int */
    private $_variation = null;

    protected function __construct(array $values, $variation) {
        $this->_values = $values;
        $this->_variation = $variation;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Target($v['values'], $v['variation']);
        };
    }

    /**
     * @return \string[]
     */
    public function getValues() {
        return $this->_values;
    }

    /**
     * @return int
     */
    public function getVariation() {
        return $this->_variation;
    }
}

class Prerequisite {
    /** @var string */
    private $_key = null;
    /** @var int */
    private $_variation = null;

    protected function __construct($key, $variation) {
        $this->_key = $key;
        $this->_variation = $variation;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Prerequisite($v['key'], $v['variation']);
        };
    }

    /**
     * @return string
     */
    public function getKey() {
        return $this->_key;
    }

    /**
     * @return int
     */
    public function getVariation() {
        return $this->_variation;
    }
}

class Rollout {
    /** @var WeightedVariation[] */
    private $_variations = array();
    /** @var string */
    private $_bucketBy = null;

    protected function __construct(array $variations, $bucketBy) {
        $this->_variations = $variations;
        $this->_bucketBy = $bucketBy;
    }

    public static function getDecoder() {
        return function ($v) {
            return new Rollout($v['variations'], $v['bucketBy']);
        };
    }

    /**
     * @return WeightedVariation[]
     */
    public function getVariations() {
        return $this->_variations;
    }

    /**
     * @return string
     */
    public function getBucketBy() {
        return $this->_bucketBy;
    }
}
