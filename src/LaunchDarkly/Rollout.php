<?php
namespace LaunchDarkly;

/**
 * Internal data model class that describes a percentage rollout.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class Rollout
{
    const KIND_EXPERIMENT = 'experiment';

    /** @var WeightedVariation[] */
    private $_variations = array();
    /** @var string */
    private $_bucketBy = null;
    /** @var string */
    private $_kind = null;
    /** @var int|null */
    private $_seed = null;

    protected function __construct(array $variations, $bucketBy, $kind = null, $seed = null)
    {
        $this->_variations = $variations;
        $this->_bucketBy = $bucketBy;
        $this->_kind = $kind;
        $this->_seed = $seed;
    }

    public static function getDecoder()
    {
        return function ($v) {
            return new Rollout(
                array_map(WeightedVariation::getDecoder(), $v['variations']),
                isset($v['bucketBy']) ? $v['bucketBy'] : null,
                isset($v['kind']) ? $v['kind'] : null,
                isset($v['seed']) ? $v['seed'] : null
            );
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

    /**
     * @return int|null
     */
    public function getSeed()
    {
        return $this->_seed;
    }

    /**
     * @return boolean
     */
    public function isExperiment()
    {
        return $this->_kind === self::KIND_EXPERIMENT;
    }
}
