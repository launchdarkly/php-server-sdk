<?php

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\LDContext;

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
    /** @var int */
    private static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    /** @var int | null */
    private $_variation = null;
    /** @var Rollout | null */
    private $_rollout = null;

    protected function __construct(?int $variation, ?Rollout $rollout)
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

    public function variationIndexForContext(LDContext $context, string $_key, ?string $_salt): array
    {
        if ($this->_variation !== null) {
            return [$this->_variation, false];
        }
        $rollout = $this->_rollout;
        if ($rollout === null) {
            return [null, false];
        }
        $variations = $rollout->getVariations();
        if ($variations) {
            $bucketBy = $rollout->getBucketBy() ?? "key";
            $bucket = self::bucketContext($context, $_key, $bucketBy, $_salt, $rollout->getSeed());
            $sum = 0.0;
            foreach ($variations as $wv) {
                $sum += $wv->getWeight() / 100000.0;
                if ($bucket < $sum) {
                    return [$wv->getVariation(), $rollout->isExperiment() && !$wv->isUntracked()];
                }
            }
            $lastVariation = $variations[count($variations) - 1];
            return [$lastVariation->getVariation(), $rollout->isExperiment() && !$lastVariation->isUntracked()];
        }
        return [null, false];
    }

    public static function bucketContext(
        LDContext $context,
        string $_key,
        string $attr,
        ?string $_salt,
        ?int $seed
    ): float {
        $contextValue = $context->get($attr);
        if ($contextValue === null) {
            return 0.0;
        }
        if (is_int($contextValue)) {
            $contextValue = (string) $contextValue;
        } elseif (!is_string($contextValue)) {
            return 0.0;
        }
        $idHash = $contextValue;
        if (isset($seed)) {
            $prefix = (string) $seed;
        } else {
            $prefix = $_key . "." . ($_salt ?? '');
        }
        $hash = substr(sha1($prefix . "." . $idHash), 0, 15);
        $longVal = (int)base_convert($hash, 16, 10);
        $result = $longVal / self::$LONG_SCALE;

        return $result;
    }
}
