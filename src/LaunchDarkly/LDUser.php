<?php
namespace LaunchDarkly;

/**
 * Contains specific attributes of a user browsing your site. The only mandatory property property is the key,
 * which must uniquely identify each user. For authenticated users, this may be a username or e-mail address. For anonymous users,
 * this could be an IP address or session ID.
 */
class LDUser {
    protected $_key = null;
    protected $_secondary = null;
    protected $_ip = null;
    protected $_country = null;
    protected $_custom = [];

    /**
     * @param string      $key       Unique key for the user. For authenticated users, this may be a username or e-mail address. For anonymous users, this could be an IP address or session ID.
     * @param string|null $secondary An optional secondary identifier
     * @param string|null $ip        The user's IP address (optional)
     * @param string|null $country   The user's country, as an ISO 3166-1 alpha-2 code (e.g. 'US') (optional)
     * @param array       $custom    Other custom attributes that can be used to create custom rules
     */
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
