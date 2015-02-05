<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class Variation {
    protected $_value = null;
    protected $_weight = 0;
    protected $_targetRule = null;
    protected $_userTarget = null;
    protected $_targets = [];

    public function __construct($value, $weight, $targets, $userTarget) {
        $this->_value   = $value;
        $this->_weight  = $weight;
        $this->_targets = $targets;
        $this->_userTarget = $userTarget;
    }

    public function matchUser($user) {
        if ($this->_userTarget != null) {
            return $this->_userTarget->matchTarget($user);
        }
        return false;
    }

    public function matchTarget($user) {
        foreach($this->_targets as $target) {
            if ($this->_userTarget != null && $target->_attribute == "key") {
                continue;
            }
            if ($target->matchTarget($user)) {
                return true;
            }
        }
        return false;
    }

    public function getValue() {
        return $this->_value;
    }

    public function getWeight() {
        return $this->_weight;
    }
}