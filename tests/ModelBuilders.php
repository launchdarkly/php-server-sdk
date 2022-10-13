<?php

namespace LaunchDarkly\Tests;

use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Rollout;
use LaunchDarkly\Impl\Model\Rule;
use LaunchDarkly\Impl\Model\SegmentRule;
use LaunchDarkly\Impl\Model\WeightedVariation;
use LaunchDarkly\LDContext;

class ModelBuilders
{
    public static function flagBuilder(string $key)
    {
        return new FlagBuilder($key);
    }

    public static function flagRuleBuilder()
    {
        return new FlagRuleBuilder();
    }

    public static function segmentBuilder(string $key)
    {
        return new SegmentBuilder($key);
    }

    public static function segmentRuleBuilder()
    {
        return new SegmentRuleBuilder();
    }

    public static function booleanFlagWithRules(Rule ...$rules): FeatureFlag
    {
        return self::flagBuilder('feature')->on(true)->variations(false, true)
            ->offVariation(0)->fallthroughVariation(0)
            ->rules($rules)->build();
    }

    public static function booleanFlagWithClauses(Clause ...$clauses): FeatureFlag
    {
        return self::booleanFlagWithRules(self::flagRuleBuilder()->variation(1)->clauses($clauses)->build());
    }

    public static function clause(string $attribute, string $op, ...$values): Clause
    {
        return new Clause($attribute, $op, $values, false);
    }

    public static function clauseMatchingContext($context): Clause
    {
        return new Clause('key', 'in', [$context->getKey()], false);
    }

    public static function clauseMatchingSegment($segment): Clause
    {
        return new Clause('', 'segmentMatch', [$segment->getKey()], false);
    }

    public static function flagRuleMatchingContext(int $variation, LDContext $context): Rule
    {
        return self::flagRuleWithClauses($variation, self::clauseMatchingContext($context));
    }

    public static function flagRuleWithClauses(int $variation, Clause ...$clauses): Rule
    {
        return self::flagRuleBuilder()->variation($variation)->clauses($clauses)->build();
    }
    
    public static function negate(Clause $clause): Clause
    {
        return new Clause($clause->getAttribute(), $clause->getOp(), $clause->getValues(), true);
    }

    public static function rolloutWithVariations(WeightedVariation ...$variations)
    {
        return new Rollout($variations, null);
    }

    public static function segmentRuleMatchingContext(LDContext $context): SegmentRule
    {
        return self::segmentRuleBuilder()->clause(self::clauseMatchingContext($context))->build();
    }

    public static function weightedVariation(int $variation, int $weight, bool $untracked = false): WeightedVariation
    {
        return new WeightedVariation($variation, $weight, $untracked);
    }
}
