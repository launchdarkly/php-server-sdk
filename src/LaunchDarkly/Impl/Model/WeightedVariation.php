<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

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
    private int $_variation;
    private int $_weight;
    private bool $_untracked = false;

    public function __construct(int $variation, int $weight, bool $untracked)
    {
        $this->_variation = $variation;
        $this->_weight = $weight;
        $this->_untracked = $untracked;
    }

    /**
     * @psalm-return \Closure(array):self
     */
    public static function getDecoder(): \Closure
    {
        return fn (array $v) => new WeightedVariation(
            (int)$v['variation'],
            (int)$v['weight'],
            $v['untracked'] ?? false
        );
    }

    public function getVariation(): int
    {
        return $this->_variation;
    }

    public function getWeight(): int
    {
        return $this->_weight;
    }

    public function isUntracked(): bool
    {
        return $this->_untracked;
    }
}
