<?php
namespace LaunchDarkly;

class Variation {
    protected $_value = null;
    protected $_weight = 0;
    protected $_targets = [];

    public function __construct($value, $weight, $targets) {
        $this->_value   = $value;
        $this->_weight  = $weight;
        $this->_targets = $targets;
    }

    public function matchTarget($user) {
        foreach($this->_targets as $target) {
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