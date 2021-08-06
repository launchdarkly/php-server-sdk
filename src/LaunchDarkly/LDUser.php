<?php
namespace LaunchDarkly;

/**
 * Contains specific attributes of a user browsing your site.
 *
 * The only mandatory property property is the key, which must uniquely identify each user. For authenticated users,
 * this may be a username or e-mail address. For anonymous users, it could be an IP address or session ID.
 *
 * Use {@see \LaunchDarkly\LDUserBuilder} to construct instances of this class.
 */
class LDUser
{
    /** @var string */
    protected $_key;

    /** @var string|null */
    protected $_secondary = null;

    /** @var string|null */
    protected $_ip = null;

    /** @var string|null */
    protected $_country = null;

    /** @var string|null */
    protected $_email = null;

    /** @var string|null */
    protected $_name = null;

    /** @var string|null */
    protected $_avatar = null;

    /** @var string|null */
    protected $_firstName = null;

    /** @var string|null */
    protected $_lastName = null;

    /** @var bool|null */
    protected $_anonymous = false;

    /** @var array|null */
    protected $_custom = array();

    /** @var array|null */
    protected $_privateAttributeNames = array();

    /**
     * Constructor for directly creating an instance.
     * 
     * It is preferable to use {@see LDUserBuilder} instead of this constructor.
     *
     * @param string $key Unique key for the user. For authenticated users, this may be a username or e-mail address. For anonymous users, this could be an IP address or session ID.
     * @param string|null $secondary An optional secondary identifier
     * @param string|null $ip The user's IP address (optional)
     * @param string|null $country The user's country, as an ISO 3166-1 alpha-2 code (e.g. 'US') (optional)
     * @param string|null $email The user's e-mail address (optional)
     * @param string|null $name The user's full name (optional)
     * @param string|null $avatar A URL pointing to the user's avatar image (optional)
     * @param string|null $firstName The user's first name (optional)
     * @param string|null $lastName The user's last name (optional)
     * @param boolean|null $anonymous Whether this is an anonymous user
     * @param array|null $custom Other custom attributes that can be used to create custom rules
     * @return LDUser
     */
    public function __construct(
        string $key, ?string $secondary = null, 
        ?string $ip = null, ?string $country = null, 
        ?string $email = null, ?string $name = null, 
        ?string $avatar = null, ?string $firstName = null, 
        ?string $lastName = null, ?bool $anonymous = null, 
        ?array $custom = array(), ?array $privateAttributeNames = array())
    {
        $this->_key = $key;
        $this->_secondary = $secondary;
        $this->_ip = $ip;
        $this->_country = $country;
        $this->_email = $email;
        $this->_name = $name;
        $this->_avatar = $avatar;
        $this->_firstName = $firstName;
        $this->_lastName = $lastName;
        $this->_anonymous = $anonymous;
        $this->_custom = $custom;
        $this->_privateAttributeNames = $privateAttributeNames;
    }

    /**
     * Used internally in flag evaluation.
     * @ignore
     * @return mixed|null
     */
    public function getValueForEvaluation(?string $attr)
    {
        if (is_null($attr)) {
            return null;
        }
        switch ($attr) {
            case "key":
                return $this->getKey();
            case "secondary": //not available for evaluation.
                return null;
            case "ip":
                return $this->getIP();
            case "country":
                return $this->getCountry();
            case "email":
                return $this->getEmail();
            case "name":
                return $this->getName();
            case "avatar":
                return $this->getAvatar();
            case "firstName":
                return $this->getFirstName();
            case "lastName":
                return $this->getLastName();
            case "anonymous":
                return $this->getAnonymous();
            default:
                $custom = $this->getCustom();
                if (is_null($custom)) {
                    return null;
                }
                if (!array_key_exists($attr, $custom)) {
                    return null;
                }
                return $custom[$attr];
        }
    }

    public function getCountry(): ?string
    {
        return $this->_country;
    }

    public function getCustom(): ?array
    {
        return $this->_custom;
    }

    public function getIP(): ?string
    {
        return $this->_ip;
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    public function getSecondary(): ?string
    {
        return $this->_secondary;
    }

    public function getEmail(): ?string
    {
        return $this->_email;
    }

    public function getName(): ?string
    {
        return $this->_name;
    }

    public function getAvatar(): ?string
    {
        return $this->_avatar;
    }

    public function getFirstName(): ?string
    {
        return $this->_firstName;
    }

    public function getLastName(): ?string
    {
        return $this->_lastName;
    }

    public function getAnonymous(): ?bool
    {
        return $this->_anonymous;
    }

    public function getPrivateAttributeNames(): ?array
    {
        return $this->_privateAttributeNames;
    }
    
    public function isKeyBlank(): bool
    {
        return empty($this->_key);
    }
}
