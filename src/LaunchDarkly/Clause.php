<?php

namespace LaunchDarkly;

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
        if ($userValue === null) {
            error_log("null user value");
            return false;
        }
        if (is_array($userValue)) {
            error_log("uservalue is array");
            foreach ($userValue as $element) {
                if ($this->matchAny($element)) {
                    return $this->_maybeNegate(true);
                }
            }
            return $this->_maybeNegate(false);
        } else {
            error_log("else...");
            return $this->_maybeNegate($this->matchAny($userValue));
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
            $result = Operators::apply($this->_op, $userValue, $v);
            error_log("clause.matchany operator result for v: $v $result");
            if ($result) {
                error_log("true for $userValue");
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