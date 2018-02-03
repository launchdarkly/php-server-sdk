<?php
namespace LaunchDarkly;

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
            if ($rule->matchesUser($user, $this->key, $this->salt)) {
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

class SegmentRule
{
    /** @var Clause[] */
    private $_clauses = array();
    /** @var int */
    private $_weight = null;
    /** @var string */
    private $_bucketBy = null;

    protected function __construct(array $clauses, $weight, $bucketBy)
    {
        $this->_clauses = $clauses;
        $this->_weight = $weight;
        $this->_bucketBy = $bucketBy;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new SegmentRule(
                array_map(Clause::getDecoder(), $v['clauses'] ?: []),
                isset($v['weight']) ? $v['weight'] : null,
                isset($v['bucketBy']) ? $v['bucketBy'] : null);
        };
    }

    public function matchesUser($user, $segmentKey, $segmentSalt)
    {
        for ($this->_clauses as $clause) {
            if (!$clause->matchesUserNoSegments($user)) {
                return false;
            }
        }
        // If the weight is absent, this rule matches
        if ($this->_weight === null) {
            return true;
        }
        // All of the clauses are met. See if the user buckets in
        $bucketBy = ($this->_bucketBy === null) ? "key" : bucketBy;
        $bucket = VariationOrRollout::bucketUser($user, $segmentKey, $bucketBy, $segmentSalt);
        $weight = $this->_weight / 100000.0;
        return $bucket < $weight;
    }

    /**
     * @return Clause[]
     */
    public function getClauses()
    {
        return $this->_clauses;
    }

    /**
     * @return string
     */
    public function getBucketBy()
    {
        return $this->_bucketBy;
    }
}
