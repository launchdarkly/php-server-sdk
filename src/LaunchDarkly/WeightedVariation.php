<?php
namespace LaunchDarkly;

/**
 * Internal data model class that describes a variation within a percentage rollout.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class WeightedVariation
{
    /** @var int */
    private $_variation = null;
    /** @var int */
    private $_weight = null;
    /** @var boolean */
    private $_untracked = false;

    private function __construct($variation, $weight, $untracked)
    {
        $this->_variation = $variation;
        $this->_weight = $weight;
        $this->_untracked = $untracked;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new WeightedVariation(
                $v['variation'],
                $v['weight'],
                isset($v['untracked']) ? $v['untracked'] : false
            );
        };
    }

    /**
     * @return int
     */
    public function getVariation()
    {
        return $this->_variation;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->_weight;
    }

    /**
     * @return boolean
     */
    public function isUntracked()
    {
        return $this->_untracked;
    }
}
