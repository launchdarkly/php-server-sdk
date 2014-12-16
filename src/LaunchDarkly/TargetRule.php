<?php
namespace LaunchDarkly;

class TargetRule {
    protected $_attribute = null;
    protected $_operator = null;
    protected $_values = [];

    public function __construct($attribute, $operator, $values) {
        $this->_attribute = $attribute;
        $this->_operator  = $operator;
        $this->_values    = $values;
    }


    public function matchTarget($user) {
        $u_value = null;

        switch ($this->_attribute) {
            case "key":
                $u_value = $user->getKey();
                break;
            case "ip":
                $u_value = $user->getIP();
                break;
            case "country":
                $u_value = $user->getCountryCode();
                break;
            default: 
                $custom = $user->getCustom();
                if (is_array($custom)) {
                    foreach ($custom as $elt) {
                        if (in_array($elt, $this->_values)) {
                            return true;
                        }
                    }
                    return false;
                } else {
                    $u_value = $custom;
                }
                break;
        }

        return isset($u_value) && in_array($u_value, $this->_values);
    }
}