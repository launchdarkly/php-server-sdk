<?php
namespace LaunchDarkly;

class LDUserBuilder {
    protected $_key = null;
    protected $_secondary = null;
    protected $_ip = null;
    protected $_country = null;
    protected $_email = null;
    protected $_name = null;
    protected $_avatar = null;
    protected $_firstName = null;
    protected $_lastName = null;
    protected $_anonymous = false;
    protected $_custom = [];    

    public function __construct($key) {
        $this->_key = $key;
    }

    public function secondary($secondary) {
        $this->_secondary = $secondary;
        return $this;
    }

    public function ip($ip) {
        $this->_ip = $ip;
        return $this;
    }

    public function country($country) {
        $this->_country = $country;
        return $this;
    }

    public function email($email) {
        $this->_email = $email;
        return $this;
    }

    public function name($name) {
        $this->_name = $name;
        return $this;
    }

    public function avatar($avatar) {
        $this->_avatar = $avatar;
        return $this;
    }

    public function firstName($firstName) {
        $this->_firstName = $firstName;
        return $this;
    }

    public function lastName($lastName) {
        $this->_lastName = $lastName;
        return $this;
    }

    public function anonymous($anonymous) {
        $this->_anonymous = $anonymous;
        return $this;
    }

    public function custom($custom) {
        $this->_custom = $custom;
        return $this;
    }

    public function build() {
        return new LDUser($this->_key, $this->_secondary, $this->_ip, $this->_country, $this->_email, $this->_name, $this->_avatar, $this->_firstName, $this->_lastName, $this->_anonymous, $this->_custom);
    }

}