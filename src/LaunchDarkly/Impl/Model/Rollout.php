<?php
namespace LaunchDarkly\Impl\Model;

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
    private $_variations = [];
    /** @var string|null */
    private $_bucketBy = null;
    /** @var string */
    private $_kind;
    /** @var int|null */
    private $_seed = null;

    protected function __construct(
        array $variations,
        ?string $bucketBy,
        ?string $kind = null,
        ?int $seed = null)
    {
        $this->_variations = $variations;
        $this->_bucketBy = $bucketBy;
        $this->_kind = $kind ?? 'rollout';
        $this->_seed = $seed;
    }

    /**
     * @psalm-return \Closure(array):self
     */
    public static function getDecoder(): \Closure
    {
        return function (array $v) {
            $decoder = WeightedVariation::getDecoder();
            $vars = array_map($decoder, $v['variations']);
            $bucket = $v['bucketBy'] ?? null;
            
            return new Rollout($vars, $bucket, $v['kind'] ?? null, $v['seed'] ?? null);
        };
    }

    /**
     * @return WeightedVariation[]
     */
    public function getVariations(): array
    {
        return $this->_variations;
    }

    public function getBucketBy(): ?string
    {
        return $this->_bucketBy;
    }

    public function getSeed(): ?int
    {
        return $this->_seed;
    }

    public function isExperiment(): bool
    {
        return $this->_kind === self::KIND_EXPERIMENT;
    }
}
