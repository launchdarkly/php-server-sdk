<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\LDContext;

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

    protected function __construct(array $clauses, ?int $weight, ?string $bucketBy)
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

    public function matchesContext(LDContext $context, string $segmentKey, string $segmentSalt): bool
    {
        foreach ($this->_clauses as $clause) {
            if (!$clause->matchesContextNoSegments($context)) {
                return false;
            }
        }
        // If the weight is absent, this rule matches
        if ($this->_weight === null) {
            return true;
        }
        // All of the clauses are met. See if the user buckets in
        $bucketBy = ($this->_bucketBy === null) ? "key" : $this->_bucketBy;
        $bucket = VariationOrRollout::bucketContext($context, $segmentKey, $bucketBy, $segmentSalt, null);
        $weight = $this->_weight / 100000.0;
        return $bucket < $weight;
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
}
