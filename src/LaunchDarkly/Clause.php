<?php

namespace LaunchDarkly;

class Clause
{
    /** @var string */
    private $_attribute = null;
    /** @var string */
    private $_op = null;
    /** @var array  */
    private $_values = array();
    /** @var bool  */
    private $_negate = false;

    private function __construct($attribute, $op, array $values, $negate)
    {
        $this->_attribute = $attribute;
        $this->_op = $op;
        $this->_values = $values;
        $this->_negate = $negate;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Clause($v['attribute'], $v['op'], $v['values'], $v['negate']);
        };
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchesUser($user)
    {
        $userValue = $user->getValueForEvaluation($this->_attribute);
        if ($userValue === null) {
            return false;
        }
        if (is_array($userValue)) {
            foreach ($userValue as $element) {
                if ($this->matchAny($element)) {
                    return $this->_maybeNegate(true);
                }
            }
            return $this->_maybeNegate(false);
        } else {
            return $this->_maybeNegate($this->matchAny($userValue));
        }
    }


    /**
     * @return string
     */
    public function getAttribute()
    {
        return $this->_attribute;
    }

    /**
     * @return string
     */
    public function getOp()
    {
        return $this->_op;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @return boolean
     */
    public function isNegate()
    {
        return $this->_negate;
    }

    /**
     * @param $userValue
     * @return bool
     */
    private function matchAny($userValue)
    {
        foreach ($this->_values as $v) {
            $result = Operators::apply($this->_op, $userValue, $v);
            if ($result === true) {
                return true;
            }
        }
        return false;
    }

    private function _maybeNegate($b)
    {
        if ($this->_negate) {
            return !$b;
        } else {
            return $b;
        }
    }
}
