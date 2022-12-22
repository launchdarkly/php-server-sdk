<?php

declare(strict_types=1);

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
    protected string $_key;
    protected ?string $_ip = null;
    protected ?string $_country = null;
    protected ?string $_email = null;
    protected ?string $_name = null;
    protected ?string $_avatar = null;
    protected ?string $_firstName = null;
    protected ?string $_lastName = null;
    protected ?bool $_anonymous = null;
    protected array $_custom = [];
    protected array $_privateAttributeNames = [];
    
    /**
     * Creates a builder with the specified key.
     * @param string $key The user key
     */
    public function __construct(string $key)
    {
        $this->_key = $key;
    }

    /**
     * Sets the user's IP address attribute.
     * @param string|null $ip The IP address
     * @return LDUserBuilder the same builder
     */
    public function ip(?string $ip): LDUserBuilder
    {
        $this->_ip = $ip;
        return $this;
    }

    /**
     * Sets the user's IP address attribute, and marks it as private.
     * @param string|null $ip The IP address
     * @return LDUserBuilder the same builder
     */
    public function privateIp(?string $ip): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'ip';
        return $this->ip($ip);
    }

    /**
     * Sets the user's country attribute.
     *
     * This may be an ISO 3166-1 country code, or any other value you wish; it is not validated.
     * @param string|null $country The country
     * @return LDUserBuilder the same builder
     */
    public function country(?string $country): LDUserBuilder
    {
        $this->_country = $country;
        return $this;
    }

    /**
     * Sets the user's country attribute, and marks it as private.
     *
     * This may be an ISO 3166-1 country code, or any other value you wish; it is not validated.
     * @param string|null $country The country
     * @return LDUserBuilder the same builder
     */
    public function privateCountry(?string $country): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'country';
        return $this->country($country);
    }

    /**
     * Sets the user's email address attribute.
     * @param string|null $email The email address
     * @return LDUserBuilder the same builder
     */
    public function email(?string $email): LDUserBuilder
    {
        $this->_email = $email;
        return $this;
    }

    /**
     * Sets the user's email address attribute, and marks it as private.
     * @param string|null $email The email address
     * @return LDUserBuilder the same builder
     */
    public function privateEmail(?string $email): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'email';
        return $this->email($email);
    }

    /**
     * Sets the user's full name attribute.
     * @param string|null $name The full name
     * @return LDUserBuilder the same builder
     */
    public function name(?string $name): LDUserBuilder
    {
        $this->_name = $name;
        return $this;
    }

    /**
     * Sets the user's full name attribute, and marks it as private.
     * @param string|null $name The full name
     * @return LDUserBuilder the same builder
     */
    public function privateName(?string $name): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'name';
        return $this->name($name);
    }

    /**
     * Sets the user's avatar URL attribute.
     * @param string|null $avatar The avatar URL
     * @return LDUserBuilder the same builder
     */
    public function avatar(?string $avatar)
    {
        $this->_avatar = $avatar;
        return $this;
    }

    /**
     * Sets the user's avatar URL attribute, and marks it as private.
     * @param string|null $avatar The avatar URL
     * @return LDUserBuilder the same builder
     */
    public function privateAvatar(?string $avatar): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'avatar';
        return $this->avatar($avatar);
    }

    /**
     * Sets the user's first name attribute.
     * @param string|null $firstName The first name
     * @return LDUserBuilder the same builder
     */
    public function firstName(?string $firstName): LDUserBuilder
    {
        $this->_firstName = $firstName;
        return $this;
    }

    /**
     * Sets the user's first name attribute, and marks it as private.
     * @param string|null $firstName The first name
     * @return LDUserBuilder the same builder
     */
    public function privateFirstName(?string $firstName): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'firstName';
        return $this->firstName($firstName);
    }

    /**
     * Sets the user's last name attribute.
     * @param string|null $lastName The last name
     * @return LDUserBuilder the same builder
     */
    public function lastName(?string $lastName): LDUserBuilder
    {
        $this->_lastName = $lastName;
        return $this;
    }

    /**
     * Sets the user's last name attribute, and marks it as private.
     * @param string|null $lastName The last name
     * @return LDUserBuilder the same builder
     */
    public function privateLastName(?string $lastName): LDUserBuilder
    {
        $this->_privateAttributeNames[] = 'lastName';
        return $this->lastName($lastName);
    }

    /**
     * Sets whether this user is anonymous.
     *
     * The default is false.
     * @param bool|null $anonymous True if the user should not appear on the LaunchDarkly dashboard
     * @return LDUserBuilder the same builder
     */
    public function anonymous(?bool $anonymous): LDUserBuilder
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
    public function custom(array $custom): LDUserBuilder
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
    public function customAttribute(string $customKey, mixed $customValue): LDUserBuilder
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
    public function privateCustomAttribute(string $customKey, mixed $customValue): LDUserBuilder
    {
        $this->_privateAttributeNames[] = $customKey;
        return $this->customAttribute($customKey, $customValue);
    }

    /**
     * Creates the LDUser instance based on the builder's current properties.
     * @return LDUser the user
     */
    public function build(): LDUser
    {
        return new LDUser(
            $this->_key,
            null,
            $this->_ip,
            $this->_country,
            $this->_email,
            $this->_name,
            $this->_avatar,
            $this->_firstName,
            $this->_lastName,
            $this->_anonymous,
            $this->_custom,
            $this->_privateAttributeNames
        );
    }
}
