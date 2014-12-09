<?php

class LDUser {
    protected $_key = null;
    protected $_secondary = null;
    protected $_ip = null;
    protected $_country = null;
    protected $_custom = [];

    public function __construct($key, $secondary = null, $ip = null, $country = null, $custom = []) {
        $this->_key = $key;
        $this->_secondary = $secondary;
        $this->_ip = $ip;
        $this->_country = $country;
        $this->_custom = $custom;
    }


    public function getCountryCode() {
        return $this->_country;
    }

    public function getCustom() {
        return $this->_custom;
    }

    public function getIP() {
        return $this->_ip;
    }

    public function getKey() {
        return $this->_key;
    }

    public function getSecondary() {
        return $this->_secondary;
    }
}
