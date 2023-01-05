<?php

declare(strict_types=1);

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
    private array $_variations = [];
    private ?string $_bucketBy = null;
    private string $_kind;
    private ?int $_seed = null;
    private ?string $_contextKind = null;

    public function __construct(
        array $variations,
        ?string $bucketBy,
        ?string $kind = null,
        ?int $seed = null,
        ?string $contextKind = null
    ) {
        $this->_variations = $variations;
        $this->_bucketBy = $bucketBy;
        $this->_kind = $kind ?: 'rollout';
        $this->_seed = $seed;
        $this->_contextKind = $contextKind;
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
            
            return new Rollout($vars, $bucket, $v['kind'] ?? null, $v['seed'] ?? null, $v['contextKind'] ?? null);
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

    public function getContextKind(): ?string
    {
        return $this->_contextKind;
    }
}
