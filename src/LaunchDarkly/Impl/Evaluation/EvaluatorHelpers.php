<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Target;
use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDContext;
use LaunchDarkly\Types\AttributeReference;

/**
 * Low-level helpers for producing various kinds of evaluation results. We also put any
 * helpers here that are used by Evaluator if they are static, i.e. if they can be
 * implemented without reference to the Evaluator instance's own state, so as to keep the
 * Evaluator logic smaller and easier to follow.
 * @ignore
 * @internal
 */
class EvaluatorHelpers
{
    public static function contextKeyIsInTargetList(LDContext $context, ?string $contextKind, array $keys): bool
    {
        if (count($keys) === 0) {
            return false;
        }
        $matchContext = $context->getIndividualContext($contextKind ?: LDContext::DEFAULT_KIND);
        return $matchContext !== null && in_array($matchContext->getKey(), $keys);
    }
  
    public static function evaluationDetailForVariation(
        FeatureFlag $flag,
        int $index,
        EvaluationReason $reason
    ): EvaluationDetail {
        $vars = $flag->getVariations();
        if ($index < 0 || $index >= count($vars)) {
            return new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        }
        return new EvaluationDetail($vars[$index], $index, $reason);
    }

    public static function getContextValueForAttributeReference(
        LDContext $context,
        string $attributeRef,
        ?string $forContextKind
    ): mixed {
        $parsed = ($forContextKind === null || $forContextKind === '') ?
            // If no context kind was specified, treat the attribute as just an attribute name, not a reference path
            AttributeReference::fromLiteral($attributeRef) :
            // If a context kind was specified, parse it as a path
            AttributeReference::fromPath($attributeRef);
        if (($err = $parsed->getError()) !== null) {
            throw new InvalidAttributeReferenceException($err);
        }
        $depth = $parsed->getDepth();
        $value = $context->get($parsed->getComponent(0));
        if ($depth <= 1) {
            return $value;
        }
        for ($i = 1; $i < $depth; $i++) {
            $propName = $parsed->getComponent($i);
            if (is_object($value)) {
                $value = get_object_vars($value)[$propName] ?? null;
            } elseif (is_array($value)) {
                // Note that either a JSON array or a JSON object could be represented as a PHP array.
                // There is no good way to distinguish between ["a", "b"] and {"0": "a", "1": "b"}.
                // Therefore, our lookup logic here is slightly more permissive than other SDKs, where
                // an attempt to get /attr/0 would only work in the second case and not in the first.
                $value = $value[$propName] ?? null;
            } else {
                return null;
            }
        }
        return $value;
    }

    public static function getOffResult(FeatureFlag $flag, EvaluationReason $reason): EvalResult
    {
        $offVar = $flag->getOffVariation();
        if ($offVar === null) {
            return new EvalResult(new EvaluationDetail(null, null, $reason), false);
        }
        return new EvalResult(self::evaluationDetailForVariation($flag, $offVar, $reason), false);
    }

    public static function getResultForVariationOrRollout(
        FeatureFlag $flag,
        VariationOrRollout $r,
        bool $forceTracking,
        LDContext $context,
        EvaluationReason $reason
    ): EvalResult {
        try {
            list($index, $inExperiment) = EvaluatorBucketing::variationIndexForContext($r, $context, $flag->getKey(), $flag->getSalt());
        } catch (InvalidAttributeReferenceException $e) {
            return new EvalResult(new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR)));
        }
        if ($index === null) {
            return new EvalResult(
                new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR)),
                false
            );
        }
        if ($inExperiment) {
            if ($reason->getKind() === EvaluationReason::FALLTHROUGH) {
                $reason = EvaluationReason::fallthrough(true);
            } elseif ($reason->getKind() === EvaluationReason::RULE_MATCH) {
                $reason = EvaluationReason::ruleMatch($reason->getRuleIndex(), $reason->getRuleId(), true);
            }
        }
        return new EvalResult(
            EvaluatorHelpers::evaluationDetailForVariation($flag, $index, $reason),
            $inExperiment || $forceTracking
        );
    }

    public static function matchClauseWithoutSegments(Clause $clause, LDContext $context): bool
    {
        $attr = $clause->getAttribute();
        if ($attr === null) {
            return false;
        }
        if ($attr === 'kind') {
            return self::maybeNegate($clause, self::matchClauseByKind($clause, $context));
        }
        $actualContext = $context->getIndividualContext($clause->getContextKind() ?? LDContext::DEFAULT_KIND);
        if ($actualContext === null) {
            return false;
        }
        $contextValue = self::getContextValueForAttributeReference($actualContext, $attr, $clause->getContextKind());
        if ($contextValue === null) {
            return false;
        }
        if (is_array($contextValue)) {
            foreach ($contextValue as $element) {
                if (self::matchAnyClauseValue($clause, $element)) {
                    return EvaluatorHelpers::maybeNegate($clause, true);
                }
            }
            return self::maybeNegate($clause, false);
        } else {
            return self::maybeNegate($clause, self::matchAnyClauseValue($clause, $contextValue));
        }
    }

    private static function matchClauseByKind(Clause $clause, LDContext $context): bool
    {
        // If attribute is "kind", then we treat operator and values as a match expression against a list
        // of all individual kinds in the context. That is, for a multi-kind context with kinds of "org"
        // and "user", it is a match if either of those strings is a match with Operator and Values.
        for ($i = 0; $i < $context->getIndividualContextCount(); $i++) {
            $c = $context->getIndividualContext($i);
            if ($c !== null && self::matchAnyClauseValue($clause, $c->getKind())) {
                return true;
            }
        }
        return false;
    }

    private static function matchAnyClauseValue(Clause $clause, mixed $contextValue): bool
    {
        $op = $clause->getOp();
        foreach ($clause->getValues() as $v) {
            $result = Operators::apply($op, $contextValue, $v);
            if ($result === true) {
                return true;
            }
        }
        return false;
    }

    public static function maybeNegate(Clause $clause, bool $b): bool
    {
        return $clause->isNegate() ? !$b : $b;
    }

    public static function targetMatchResult(FeatureFlag $flag, Target $t): EvalResult
    {
        return new EvalResult(
            self::evaluationDetailForVariation($flag, $t->getVariation(), EvaluationReason::targetMatch()),
            false
        );
    }
}
