<?php

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\LDUser;

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

    public function variationIndexForUser(LDUser $user, string $_key, ?string $_salt): array
    {
        if ($this->_variation !== null) {
            return array($this->_variation, false);
        }
        $rollout = $this->_rollout;
        if ($rollout === null) {
            return array(null, false);
        }
        $variations = $rollout->getVariations();
        if ($variations) {
            $bucketBy = $rollout->getBucketBy() ?? "key";
            $bucket = self::bucketUser($user, $_key, $bucketBy, $_salt, $rollout->getSeed());
            $sum = 0.0;
            foreach ($variations as $wv) {
                $sum += $wv->getWeight() / 100000.0;
                if ($bucket < $sum) {
                    return array($wv->getVariation(), $rollout->isExperiment() && !$wv->isUntracked());
                }
            }
            $lastVariation = $variations[count($variations) - 1];
            return array($lastVariation->getVariation(), $rollout->isExperiment() && !$lastVariation->isUntracked());
        }
        return array(null, false);
    }

    public static function bucketUser(
        LDUser $user, string $_key, 
        string $attr, ?string $_salt,
        ?int $seed
    ): float
    {
        $userValue = $user->getValueForEvaluation($attr);
        $idHash = null;
        if ($userValue != null) {
            if (is_int($userValue)) {
                $userValue = (string) $userValue;
            }
            if (is_string($userValue)) {
                $idHash = $userValue;
                if (isset($seed)) {
                    $prefix = (string) $seed;
                } else {
                    $prefix = $_key . "." . ($_salt ?? '');
                }
                if ($user->getSecondary() !== null) {
                    $idHash = $idHash . "." . strval($user->getSecondary());
                }
                $hash = substr(sha1($prefix . "." . $idHash), 0, 15);
                $longVal = (int)base_convert($hash, 16, 10);
                $result = $longVal / self::$LONG_SCALE;

                return $result;
            }
        }
        return 0.0;
    }
}
