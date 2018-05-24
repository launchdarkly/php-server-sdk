<?php
namespace LaunchDarkly;

/**
 * A <a href="http://en.wikipedia.org/wiki/Builder_pattern">builder</a> that helps construct LDUser objects.
 *
 * Note that all user attributes, except for <code>key</code> and <code>anonymous</code>, can be designated as
 * private so that they will not be sent back to LaunchDarkly. You can do this either on a per-user basis in
 * LDUserBuilder, or globally via the <code>private_attribute_names</code> and <code>all_attributes_private</code>
 * options in the client configuration.
 */
class LDUserBuilder
{
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
    
    /**
     * Creates a builder with the specified key.
     */
    public function __construct($key)
    {
        $this->_key = $key;
    }

    public function secondary($secondary)
    {
        $this->_secondary = $secondary;
        return $this;
    }

    public function privateSecondary($secondary)
    {
        array_push($this->_privateAttributeNames, 'secondary');
        return $this->secondary($secondary);
    }

    /**
     * Sets the IP for a user.
     */
    public function ip($ip){
        $this->_ip = $ip;
        return $this;
    }

    /**
     * Sets the IP for a user, and ensures that the IP attribute will not be sent back to LaunchDarkly.
     */
    public function privateIp($ip)
    {
        array_push($this->_privateAttributeNames, 'ip');
        return $this->ip($ip);
    }

    /**
     * Sets the country for a user. The country should be a valid <a href="http://en.wikipedia.org/wiki/ISO_3166-1">ISO 3166-1</a>
     * alpha-2 or alpha-3 code. If it is not a valid ISO-3166-1 code, an attempt will be made to look up the country by its name.
     * If that fails, a warning will be logged, and the country will not be set.
     */
    public function country($country)
    {
        $this->_country = $country;
        return $this;
    }

    /**
     * Sets the country for a user, and ensures that the country attribute will not be sent back to LaunchDarkly.
     * The country should be a valid <a href="http://en.wikipedia.org/wiki/ISO_3166-1">ISO 3166-1</a>
     * alpha-2 or alpha-3 code. If it is not a valid ISO-3166-1 code, an attempt will be made to look up the country by its name.
     * If that fails, a warning will be logged, and the country will not be set.
     */
    public function privateCountry($country)
    {
        array_push($this->_privateAttributeNames, 'country');
        return $this->country($country);
    }

    /**
     * Sets the user's email address.
     */
    public function email($email)
    {
        $this->_email = $email;
        return $this;
    }

    /**
     * Sets the user's email address, and ensures that the email attribute will not be sent back to LaunchDarkly.
     */
    public function privateEmail($email)
    {
        array_push($this->_privateAttributeNames, 'email');
        return $this->email($email);
    }

    /**
     * Sets the user's full name.
     */
    public function name($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets the user's full name, and ensures that the name attribute will not be sent back to LaunchDarkly.
     */
    public function privateName($name)
    {
        array_push($this->_privateAttributeNames, 'name');
        return $this->name($name);
    }

    /**
     * Sets the user's avatar.
     */
    public function avatar($avatar)
    {
        $this->_avatar = $avatar;
        return $this;
    }

    /**
     * Sets the user's avatar, and ensures that the avatar attribute will not be sent back to LaunchDarkly.
     */
    public function privateAvatar($avatar)
    {
        array_push($this->_privateAttributeNames, 'avatar');
        return $this->avatar($avatar);
    }

    /**
     * Sets the user's first name.
     */
    public function firstName($firstName)
    {
        $this->_firstName = $firstName;
        return $this;
    }

    /**
     * Sets the user's first name, and ensures that the first name attribute will not be sent back to LaunchDarkly.
     */
    public function privateFirstName($firstName)
    {
        array_push($this->_privateAttributeNames, 'firstName');
        return $this->firstName($firstName);
    }

    /**
     * Sets the user's last name.
     */
    public function lastName($lastName)
    {
        $this->_lastName = $lastName;
        return $this;
    }

    /**
     * Sets the user's last name, and ensures that the last name attribute will not be sent back to LaunchDarkly.
     */
    public function privateLastName($lastName)
    {
        array_push($this->_privateAttributeNames, 'lastName');
        return $this->lastName($lastName);
    }

    /**
     * Sets whether this user is anonymous. The default is false.
     */
    public function anonymous($anonymous)
    {
        $this->_anonymous = $anonymous;
        return $this;
    }

    /**
     * Sets any number of custom attributes for the user.
     * @param array $custom An associative array of custom attribute names and values.
     */
    public function custom($custom)
    {
        $this->_custom = $custom;
        return $this;
    }

    /**
     * Sets a single custom attribute for the user.
     */
    public function customAttribute($customKey, $customValue)
    {
        $this->_custom[$customKey] = $customValue;
        return $this;
    }

    /**
     * Sets a single custom attribute for the user, and ensures that the attribute will not be sent back to LaunchDarkly.
     */
    public function privateCustomAttribute($customKey, $customValue)
    {
        array_push($this->_privateAttributeNames, $customKey);
        return $this->customAttribute($customKey, $customValue);
    }

    public function build()
    {
        return new LDUser($this->_key, $this->_secondary, $this->_ip, $this->_country, $this->_email, $this->_name, $this->_avatar, $this->_firstName, $this->_lastName, $this->_anonymous, $this->_custom, $this->_privateAttributeNames);
    }
}
