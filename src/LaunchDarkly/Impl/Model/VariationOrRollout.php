<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Model;

/**
 * Internal data model class that describes a fixed variation or percentage rollout.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class VariationOrRollout
{
    private ?int $_variation = null;
    private ?Rollout $_rollout = null;

    public function __construct(?int $variation, ?Rollout $rollout)
    {
        $this->_variation = $variation;
        $this->_rollout = $rollout;
    }

    /**
     * @psalm-return \Closure(array):self
     */
    public static function getDecoder(): \Closure
    {
        return function (?array $v) {
            $decoder = Rollout::getDecoder();
            $variation = $v['variation'] ?? null;
            $rollout = isset($v['rollout']) ? $decoder($v['rollout']) : null;
            
            return new VariationOrRollout($variation, $rollout);
        };
    }

    public function getVariation(): ?int
    {
        return $this->_variation;
    }

    public function getRollout(): ?Rollout
    {
        return $this->_rollout;
    }
}
