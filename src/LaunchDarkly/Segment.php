<?php
namespace LaunchDarkly;

/**
 * Internal data model class that describes a user segment.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Segment
{
    /** @var string */
    protected $_key = null;
    /** @var int */
    protected $_version = null;
    /** @var string[] */
    protected $_included = array();
    /** @var string[] */
    protected $_excluded = array();
    /** @var string */
    protected $_salt = null;
    /** @var SegmentRule[] */
    protected $_rules = array();
    /** @var bool */
    protected $_deleted = false;

    protected function __construct($key,
                                   $version,
                                   array $included,
                                   array $excluded,
                                   $salt,
                                   array $rules,
                                   $deleted)
    {
        $this->_key = $key;
        $this->_version = $version;
        $this->_included = $included;
        $this->_excluded = $excluded;
        $this->_salt = $salt;
        $this->_rules = $rules;
        $this->_deleted = $deleted;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Segment(
                $v['key'],
                $v['version'],
                $v['included'] ?: [],
                $v['excluded'] ?: [],
                $v['salt'],
                array_map(SegmentRule::getDecoder(), $v['rules'] ?: []),
                $v['deleted']);
        };
    }

    public static function decode($v)
    {
        return call_user_func(Segment::getDecoder(), $v);
    }

    /**
     * @param $user LDUser
     * @return boolean
     */
    public function matchesUser($user)
    {
        $key = $user->getKey();
        if (!$key) {
            return false;
        }
        if (in_array($key, $this->_included, true)) {
            return true;
        }
        if (in_array($key, $this->_excluded, true)) {
            return false;
        }
        foreach ($this->_rules as $rule) {
            if ($rule->matchesUser($user, $this->_key, $this->_salt)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * @return boolean
     */
    public function isDeleted()
    {
        return $this->_deleted;
    }
}
