<?php

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\VariationOrRollout;
use LaunchDarkly\LDContext;

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
        list($index, $inExperiment) = EvaluatorBucketing::variationIndexForContext($r, $context, $flag->getKey(), $flag->getSalt());
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
        $contextValue = $actualContext->get($attr);
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
}
