<?php
namespace LaunchDarkly;

/**
 * A builder for constructing LDUser objects.
 *
 * Note that all user attributes, except for `key` and `anonymous`, can be designated as private so that
 * they will not be sent back to LaunchDarkly. You can do this either on a per-user basis in LDUserBuilder,
 * or globally via the `private_attribute_names` and `all_attributes_private` options in the client
 * configuration.
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
     * @param string $key The user key
     * @return LDUserBuilder
     */
    public function __construct($key)
    {
        $this->_key = $key;
    }

    /**
     * Sets the user's secondary key attribute.
     * @param string $secondary The secondary key
     * @return LDUserBuilder the same builder
     */
    public function secondary($secondary)
    {
        $this->_secondary = $secondary;
        return $this;
    }

    /**
     * Sets the user's secondary key attribute, and marks it as private.
     * @param string $secondary The secondary key
     * @return LDUserBuilder the same builder
     */
    public function privateSecondary($secondary)
    {
        array_push($this->_privateAttributeNames, 'secondary');
        return $this->secondary($secondary);
    }

    /**
     * Sets the user's IP address attribute.
     * @param string $ip The IP address
     * @return LDUserBuilder the same builder
     */
    public function ip($ip)
    {
        $this->_ip = $ip;
        return $this;
    }

    /**
     * Sets the user's IP address attribute, and marks it as private.
     * @param string $ip The IP address
     * @return LDUserBuilder the same builder
     */
    public function privateIp($ip)
    {
        array_push($this->_privateAttributeNames, 'ip');
        return $this->ip($ip);
    }

    /**
     * Sets the user's country attribute.
     *
     * This may be an ISO 3166-1 country code, or any other value you wish; it is not validated.
     * @param string $country The country
     * @return LDUserBuilder the same builder
     */
    public function country($country)
    {
        $this->_country = $country;
        return $this;
    }

    /**
     * Sets the user's country attribute, and marks it as private.
     *
     * This may be an ISO 3166-1 country code, or any other value you wish; it is not validated.
     * @param string $country The country
     * @return LDUserBuilder the same builder
     */
    public function privateCountry($country)
    {
        array_push($this->_privateAttributeNames, 'country');
        return $this->country($country);
    }

    /**
     * Sets the user's email address attribute.
     * @param string $email The email address
     * @return LDUserBuilder the same builder
     */
    public function email($email)
    {
        $this->_email = $email;
        return $this;
    }

    /**
     * Sets the user's email address attribute, and marks it as private.
     * @param string $email The email address
     * @return LDUserBuilder the same builder
     */
    public function privateEmail($email)
    {
        array_push($this->_privateAttributeNames, 'email');
        return $this->email($email);
    }

    /**
     * Sets the user's full name attribute.
     * @param string $name The full name
     * @return LDUserBuilder the same builder
     */
    public function name($name)
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets the user's full name attribute, and marks it as private.
     * @param string $name The full name
     * @return LDUserBuilder the same builder
     */
    public function privateName($name)
    {
        array_push($this->_privateAttributeNames, 'name');
        return $this->name($name);
    }

    /**
     * Sets the user's avatar URL attribute.
     * @param string $avatar The avatar URL
     * @return LDUserBuilder the same builder
     */
    public function avatar($avatar)
    {
        $this->_avatar = $avatar;
        return $this;
    }

    /**
     * Sets the user's avatar URL attribute, and marks it as private.
     * @param string $avatar The avatar URL
     * @return LDUserBuilder the same builder
     */
    public function privateAvatar($avatar)
    {
        array_push($this->_privateAttributeNames, 'avatar');
        return $this->avatar($avatar);
    }

    /**
     * Sets the user's first name attribute.
     * @param string $firstName The first name
     * @return LDUserBuilder the same builder
     */
    public function firstName($firstName)
    {
        $this->_firstName = $firstName;
        return $this;
    }

    /**
     * Sets the user's first name attribute, and marks it as private.
     * @param string $firstName The first name
     * @return LDUserBuilder the same builder
     */
    public function privateFirstName($firstName)
    {
        array_push($this->_privateAttributeNames, 'firstName');
        return $this->firstName($firstName);
    }

    /**
     * Sets the user's last name attribute.
     * @param string $lastName The last name
     * @return LDUserBuilder the same builder
     */
    public function lastName($lastName)
    {
        $this->_lastName = $lastName;
        return $this;
    }

    /**
     * Sets the user's last name attribute, and marks it as private.
     * @param string $lastName The last name
     * @return LDUserBuilder the same builder
     */
    public function privateLastName($lastName)
    {
        array_push($this->_privateAttributeNames, 'lastName');
        return $this->lastName($lastName);
    }

    /**
     * Sets whether this user is anonymous.
     *
     * The default is false.
     * @param bool $anonymous True if the user should not appear on the LaunchDarkly dashboard
     * @return LDUserBuilder the same builder
     */
    public function anonymous($anonymous)
    {
        $this->_anonymous = $anonymous;
        return $this;
    }

    /**
     * Sets any number of custom attributes for the user.
     *
     * @param array $custom An associative array of custom attribute names and values.
     * @return LDUserBuilder the same builder
     */
    public function custom($custom)
    {
        $this->_custom = $custom;
        return $this;
    }

    /**
     * Sets a single custom attribute for the user.
     *
     * @param string $customKey The attribute name
     * @param mixed $customValue The attribute value
     * @return LDUserBuilder the same builder
     */
    public function customAttribute($customKey, $customValue)
    {
        $this->_custom[$customKey] = $customValue;
        return $this;
    }

    /**
     * Sets a single custom attribute for the user, and marks it as private.
     *
     * @param string $customKey The attribute name
     * @param mixed $customValue The attribute value
     * @return LDUserBuilder the same builder
     */
    public function privateCustomAttribute($customKey, $customValue)
    {
        array_push($this->_privateAttributeNames, $customKey);
        return $this->customAttribute($customKey, $customValue);
    }

    /**
     * Creates the LDUser instance based on the builder's current properties.
     *
     * @return LDUser
     */
    public function build()
    {
        return new LDUser($this->_key, $this->_secondary, $this->_ip, $this->_country, $this->_email, $this->_name, $this->_avatar, $this->_firstName, $this->_lastName, $this->_anonymous, $this->_custom, $this->_privateAttributeNames);
    }
}
