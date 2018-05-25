<?php
namespace LaunchDarkly;

class Rollout
{
    /** @var WeightedVariation[] */
    private $_variations = array();
    /** @var string */
    private $_bucketBy = null;

    protected function __construct(array $variations, $bucketBy)
    {
        $this->_variations = $variations;
        $this->_bucketBy = $bucketBy;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Rollout(
                array_map(WeightedVariation::getDecoder(), $v['variations']),
                isset($v['bucketBy']) ? $v['bucketBy'] : null);
        };
    }

    /**
     * @return WeightedVariation[]
     */
    public function getVariations()
    {
        return $this->_variations;
    }

    /**
     * @return string
     */
    public function getBucketBy()
    {
        return $this->_bucketBy;
    }
}
