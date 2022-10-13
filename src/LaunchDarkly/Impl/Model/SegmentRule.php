<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

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
    private array $_clauses = [];
    private ?int $_weight = null;
    private ?string $_bucketBy = null;

    public function __construct(array $clauses, ?int $weight, ?string $bucketBy)
    {
        $this->_clauses = $clauses;
        $this->_weight = $weight;
        $this->_bucketBy = $bucketBy;
    }

    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new SegmentRule(
            array_map(Clause::getDecoder(), $v['clauses'] ?: []),
            $v['weight'] ?? null,
            $v['bucketBy'] ?? null
        );
    }

    /**
     * @return Clause[]
     */
    public function getClauses(): array
    {
        return $this->_clauses;
    }

    public function getBucketBy(): ?string
    {
        return $this->_bucketBy;
    }

    public function getWeight(): ?int
    {
        return $this->_weight;
    }
}
