<?php

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDContext;

/**
 * Encapsulates the logic for percentage rollouts and experiments.
 * @ignore
 * @internal
 */
class EvaluatorBucketing
{
    const LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    public static function variationIndexForContext(
        VariationOrRollout $vr,
        LDContext $context,
        string $_key,
        ?string $_salt
    ): array {
        $variation = $vr->getVariation();
        if ($variation !== null) {
            return [$variation, false];
        }
        $rollout = $vr->getRollout();
        if ($rollout === null) {
            return [null, false];
        }
        $variations = $rollout->getVariations();
        if ($variations) {
            $bucketBy = $rollout->getBucketBy() ?: "key";
            $bucket = self::getBucketValueForContext($context, $_key, $bucketBy, $_salt, $rollout->getSeed());
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

    public static function getBucketValueForContext(
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
            $prefix = $_key . "." . ($_salt ?: '');
        }
        $hash = substr(sha1($prefix . "." . $idHash), 0, 15);
        $longVal = (int)base_convert($hash, 16, 10);
        $result = $longVal / self::LONG_SCALE;

        return $result;
    }
}
