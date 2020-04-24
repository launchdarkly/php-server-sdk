<?php
namespace LaunchDarkly;

/**
 * Internal data model class that describes a user segment rule.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
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
        foreach ($this->_clauses as $clause) {
            if (!$clause->matchesUserNoSegments($user)) {
                return false;
            }
        }
        // If the weight is absent, this rule matches
        if ($this->_weight === null) {
            return true;
        }
        // All of the clauses are met. See if the user buckets in
        $bucketBy = ($this->_bucketBy === null) ? "key" : $this->_bucketBy;
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
