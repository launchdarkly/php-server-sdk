<?php
namespace LaunchDarkly;

/**
 * @internal
 */
class Variation {
    protected $_value = null;
    protected $_weight = 0;
    protected $_targetRule = null;

    /** @var TargetRule */
    protected $_userTarget = null;

    /** @var TargetRule[] */
    protected $_targets = array();

    public function __construct($value, $weight, $targets, $userTarget) {
        $this->_value   = $value;
        $this->_weight  = $weight;
        $this->_targets = $targets;
        $this->_userTarget = $userTarget;
    }

    /**
     * @param $user LDUser
     * @return bool
     */
    public function matchUser($user) {
        if ($this->_userTarget != null) {
            return $this->_userTarget->matchTarget($user);
        }
        return false;
    }

    public function matchTarget($user) {
        foreach($this->_targets as $target) {
            if ($this->_userTarget != null && $target->isKey()) {
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