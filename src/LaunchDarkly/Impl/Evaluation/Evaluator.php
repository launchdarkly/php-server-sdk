<?php

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\EvaluationReason;
use LaunchDarkly\FeatureRequester;
use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Rule;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\Model\SegmentRule;
use LaunchDarkly\LDContext;

/**
 * Encapsulates the feature flag evaluation logic. The Evaluator has no direct access to the
 * rest of the SDK environment; if it needs to retrieve flags or segments that are referenced
 * by a flag, it does so through a FeatureRequester that is provided in the constructor. It also
 * produces evaluation events as appropriate for any referenced prerequisite flags.
 * @ignore
 * @internal
 */
class Evaluator
{
    private FeatureRequester $_featureRequester;

    public function __construct(FeatureRequester $featureRequester)
    {
        $this->_featureRequester = $featureRequester;
    }

    /**
     * The client's entry point for evaluating a flag. No other Evaluator methods should be exposed.
     *
     * @param FeatureFlag $flag an existing feature flag; any other referenced flags or segments will be
     *   queried via the FeatureRequester
     * @param LDContext $context the evaluation context
     * @param ?callable $prereqEvalSink a function that may be called with a
     *   PrerequisiteEvaluationRecord parameter for any prerequisite flags that are evaluated as a side
     *   effect of evaluating this flag
     * @return EvalResult the outputs of evaluation
     */
    public function evaluate(FeatureFlag $flag, LDContext $context, ?callable $prereqEvalSink): EvalResult
    {
        return $this->evaluateInternal($flag, $context, $prereqEvalSink);
    }

    private function evaluateInternal(
        FeatureFlag $flag,
        LDContext $context,
        ?callable $prereqEvalSink
    ): EvalResult {
        if (!$flag->isOn()) {
            return EvaluatorHelpers::getOffResult($flag, EvaluationReason::off());
        }

        $prereqFailureReason = $this->checkPrerequisites($flag, $context, $prereqEvalSink);
        if ($prereqFailureReason !== null) {
            return EvaluatorHelpers::getOffResult($flag, $prereqFailureReason);
        }

        // Check to see if targets match
        $targetResult = $this->checkTargets($flag, $context);
        if ($targetResult) {
            return $targetResult;
        }

        // Now walk through the rules and see if any match
        foreach ($flag->getRules() as $i => $rule) {
            if ($this->ruleMatchesContext($rule, $context)) {
                return EvaluatorHelpers::getResultForVariationOrRollout(
                    $flag,
                    $rule,
                    $rule->isTrackEvents(),
                    $context,
                    EvaluationReason::ruleMatch($i, $rule->getId())
                );
            }
        }
        return EvaluatorHelpers::getResultForVariationOrRollout(
            $flag,
            $flag->getFallthrough(),
            $flag->isTrackEventsFallthrough(),
            $context,
            EvaluationReason::fallthrough()
        );
    }

    private function checkPrerequisites(
        FeatureFlag $flag,
        LDContext $context,
        ?callable $prereqEvalSink
    ): ?EvaluationReason {
        foreach ($flag->getPrerequisites() as $prereq) {
            $prereqOk = true;
            try {
                $prereqFeatureFlag = $this->_featureRequester->getFeature($prereq->getKey());
                if ($prereqFeatureFlag == null) {
                    $prereqOk = false;
                } else {
                    $prereqEvalResult = $this->evaluateInternal($prereqFeatureFlag, $context, $prereqEvalSink);
                    $variation = $prereq->getVariation();
                    if (!$prereqFeatureFlag->isOn() || $prereqEvalResult->getDetail()->getVariationIndex() !== $variation) {
                        $prereqOk = false;
                    }
                    if ($prereqEvalSink !== null) {
                        $prereqEvalSink(new PrerequisiteEvaluationRecord($prereqFeatureFlag, $flag, $prereqEvalResult));
                    }
                }
            } catch (\Exception $e) {
                $prereqOk = false;
            }
            if (!$prereqOk) {
                return EvaluationReason::prerequisiteFailed($prereq->getKey());
            }
        }
        return null;
    }

    private function checkTargets(FeatureFlag $flag, LDContext $context): ?EvalResult
    {
        $userTargets = $flag->getTargets();
        $contextTargets = $flag->getContextTargets();
        if (count($contextTargets) === 0) {
            // old-style data has only targets for users
            if (count($userTargets) !== 0) {
                $userContext = $context->getIndividualContext(LDContext::DEFAULT_KIND);
                if ($userContext === null) {
                    return null;
                }
                foreach ($userTargets as $t) {
                    if (in_array($userContext->getKey(), $t->getValues())) {
                        return EvaluatorHelpers::targetMatchResult($flag, $t);
                    }
                }
            }
            return null;
        }

        foreach ($contextTargets as $t) {
            if (($t->getContextKind() ?: LDContext::DEFAULT_KIND) === LDContext::DEFAULT_KIND) {
                $userContext = $context->getIndividualContext(LDContext::DEFAULT_KIND);
                if ($userContext === null) {
                    continue;
                }
                $userKey = $userContext->getKey();
                foreach ($userTargets as $ut) {
                    if ($ut->getVariation() === $t->getVariation()) {
                        if (in_array($userKey, $ut->getValues())) {
                            return EvaluatorHelpers::targetMatchResult($flag, $ut);
                        }
                        break;
                    }
                }
            } else {
                if (EvaluatorHelpers::contextKeyIsInTargetList($context, $t->getContextKind(), $t->getValues())) {
                    return EvaluatorHelpers::targetMatchResult($flag, $t);
                }
            }
        }

        return null;
    }

    private function ruleMatchesContext(Rule $rule, LDContext $context): bool
    {
        foreach ($rule->getClauses() as $clause) {
            if (!$this->clauseMatchesContext($clause, $context)) {
                return false;
            }
        }
        return true;
    }

    private function clauseMatchesContext(Clause $clause, LDContext $context): bool
    {
        if ($clause->getOp() === 'segmentMatch') {
            foreach ($clause->getValues() as $value) {
                $segment = $this->_featureRequester->getSegment($value);
                if ($segment) {
                    if ($this->segmentMatchesContext($segment, $context)) {
                        return EvaluatorHelpers::maybeNegate($clause, true);
                    }
                }
            }
            return EvaluatorHelpers::maybeNegate($clause, false);
        }
        return EvaluatorHelpers::matchClauseWithoutSegments($clause, $context);
    }

    private function segmentMatchesContext(Segment $segment, LDContext $context): bool
    {
        $key = $context->getKey();
        if (!$key) {
            return false;
        }
        if (in_array($key, $segment->getIncluded(), true)) {
            return true;
        }
        if (in_array($key, $segment->getExcluded(), true)) {
            return false;
        }
        foreach ($segment->getRules() as $rule) {
            if ($this->segmentRuleMatchesContext($rule, $context, $segment->getKey(), $segment->getSalt())) {
                return true;
            }
        }
        return false;
    }

    private function segmentRuleMatchesContext(
        SegmentRule $rule,
        LDContext $context,
        string $segmentKey,
        string $segmentSalt
    ): bool {
        foreach ($rule->getClauses() as $clause) {
            if (!EvaluatorHelpers::matchClauseWithoutSegments($clause, $context)) {
                return false;
            }
        }
        // If the weight is absent, this rule matches
        if ($rule->getWeight() === null) {
            return true;
        }
        // All of the clauses are met. See if the user buckets in
        $bucketBy = $rule->getBucketBy() ?: 'key';
        $bucket = EvaluatorBucketing::getBucketValueForContext($context, $segmentKey, $bucketBy, $segmentSalt, null);
        $weight = $rule->getWeight() / 100000.0;
        return $bucket < $weight;
    }
}
