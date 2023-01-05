<?php

declare(strict_types=1);

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
        if (count($variations) === 0) {
            return [null, false];
        }

        $bucketBy = ($rollout->isExperiment() ? null : $rollout->getBucketBy()) ?: 'key';
        $bucket = self::getBucketValueForContext(
            $context,
            $rollout->getContextKind(),
            $_key,
            $bucketBy,
            $_salt,
            $rollout->getSeed()
        );
        $experiment = $rollout->isExperiment() && $bucket >= 0;
        // getBucketValueForContext returns a negative value if the context didn't exist, in which case we
        // still end up returning the first bucket, but we will force the "in experiment" state to be false.

        $sum = 0.0;
        foreach ($variations as $wv) {
            $sum += $wv->getWeight() / 100000.0;
            if ($bucket < $sum) {
                return [$wv->getVariation(), $experiment && !$wv->isUntracked()];
            }
        }
        $lastVariation = $variations[count($variations) - 1];
        return [$lastVariation->getVariation(), $experiment && !$lastVariation->isUntracked()];
    }

    public static function getBucketValueForContext(
        LDContext $context,
        ?string $contextKind,
        string $key,
        string $attr,
        ?string $salt,
        ?int $seed
    ): float {
        $matchContext = $context->getIndividualContext($contextKind ?? LDContext::DEFAULT_KIND);
        if ($matchContext === null) {
            return -1;
        }
        $contextValue = EvaluatorHelpers::getContextValueForAttributeReference($matchContext, $attr, $contextKind);
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
            $prefix = $key . "." . ($salt ?: '');
        }
        $hash = substr(sha1($prefix . "." . $idHash), 0, 15);
        $longVal = (int)base_convert($hash, 16, 10);
        $result = $longVal / self::LONG_SCALE;

        return $result;
    }
}
