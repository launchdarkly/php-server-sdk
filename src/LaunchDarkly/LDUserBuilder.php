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
    protected $_anonymous = null;
    protected $_custom = array();
    protected $_privateAttributeNames = array();
    
    public function __construct($key) {
        $this->_key = $key;
    }

    public function secondary($secondary) {
        $this->_secondary = $secondary;
        return $this;
    }

    public function privateSecondary($secondary) {
        array_push($this->_privateAttributeNames, 'secondary');
        return $this->secondary($secondary);
    }

    public function ip($ip) {
        $this->_ip = $ip;
        return $this;
    }

    public function privateIp($ip) {
        array_push($this->_privateAttributeNames, 'ip');
        return $this->ip($ip);
    }

    public function country($country) {
        $this->_country = $country;
        return $this;
    }

    public function privateCountry($country) {
        array_push($this->_privateAttributeNames, 'country');
        return $this->country($country);
    }

    public function email($email) {
        $this->_email = $email;
        return $this;
    }

    public function privateEmail($email) {
        array_push($this->_privateAttributeNames, 'email');
        return $this->email($email);
    }

    public function name($name) {
        $this->_name = $name;
        return $this;
    }

    public function privateName($name) {
        array_push($this->_privateAttributeNames, 'name');
        return $this->name($name);
    }

    public function avatar($avatar) {
        $this->_avatar = $avatar;
        return $this;
    }

    public function privateAvatar($avatar) {
        array_push($this->_privateAttributeNames, 'avatar');
        return $this->avatar($avatar);
    }

    public function firstName($firstName) {
        $this->_firstName = $firstName;
        return $this;
    }

    public function privateFirstName($firstName) {
        array_push($this->_privateAttributeNames, 'firstName');
        return $this->firstName($firstName);
    }

    public function lastName($lastName) {
        $this->_lastName = $lastName;
        return $this;
    }

    public function privateLastName($lastName) {
        array_push($this->_privateAttributeNames, 'lastName');
        return $this->lastName($lastName);
    }

    public function anonymous($anonymous) {
        $this->_anonymous = $anonymous;
        return $this;
    }

    public function custom($custom) {
        $this->_custom = $custom;
        return $this;
    }

    public function customAttribute($customKey, $customValue) {
        $this->_custom[$customKey] = $customValue;
        return $this;
    }

    public function privateCustomAttribute($customKey, $customValue) {
        array_push($this->_privateAttributeNames, $customKey);
        return $this->customAttribute($customKey, $customValue);
    }

    public function build() {
        return new LDUser($this->_key, $this->_secondary, $this->_ip, $this->_country, $this->_email, $this->_name, $this->_avatar, $this->_firstName, $this->_lastName, $this->_anonymous, $this->_custom, $this->_privateAttributeNames);
    }

}