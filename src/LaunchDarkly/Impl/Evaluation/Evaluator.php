<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Evaluation;

use LaunchDarkly\BigSegmentsEvaluationStatus;
use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\BigSegments;
use LaunchDarkly\Impl\Model\Clause;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Rule;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Impl\Model\SegmentRule;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDContext;
use LaunchDarkly\Subsystems\FeatureRequester;
use Psr\Log\LoggerInterface;

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
    private BigSegments\StoreManager $_bigSegmentsStoreManager;
    private LoggerInterface $_logger;

    public function __construct(FeatureRequester $featureRequester, BigSegments\StoreManager $bigSegmentsStoreManager, ?LoggerInterface $logger = null)
    {
        $this->_featureRequester = $featureRequester;
        $this->_bigSegmentsStoreManager = $bigSegmentsStoreManager;
        $this->_logger = $logger ?: Util::makeNullLogger();
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
        $state = new EvaluatorState($flag);
        try {
            $evalResult = $this->evaluateInternal($flag, $context, $prereqEvalSink, $state)
                ->withState($state);

            if ($state->bigSegmentsEvaluationStatus !== null) {
                $reason = $evalResult->getDetail()->getReason()->withBigSegmentsEvaluationStatus($state->bigSegmentsEvaluationStatus);
                $detail = new EvaluationDetail(
                    $evalResult->getDetail()->getValue(),
                    $evalResult->getDetail()->getVariationIndex(),
                    $reason
                );

                $evalResult = $evalResult->withDetail($detail);
            }

            return $evalResult;
        } catch (EvaluationException $e) {
            return new EvalResult(new EvaluationDetail(null, null, EvaluationReason::error($e->getErrorKind())), false, $state);
        } catch (\Throwable $e) {
            Util::logExceptionAtErrorLevel($this->_logger, $e, 'Unexpected error when evaluating flag ' . $flag->getKey());
            return new EvalResult(new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::EXCEPTION_ERROR)), false, $state);
        }
    }

    private function evaluateInternal(
        FeatureFlag $flag,
        LDContext $context,
        ?callable $prereqEvalSink,
        EvaluatorState $state
    ): EvalResult {
        if (!$flag->isOn()) {
            return EvaluatorHelpers::getOffResult($flag, EvaluationReason::off());
        }

        $prereqFailureReason = $this->checkPrerequisites($flag, $context, $prereqEvalSink, $state);
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
            if ($this->ruleMatchesContext($rule, $context, $state)) {
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
        ?callable $prereqEvalSink,
        EvaluatorState $state
    ): ?EvaluationReason {
        // We use the state object to guard against circular references in prerequisites. To avoid
        // the overhead of creating the $state->prerequisiteStack array in the most common case where
        // there's only a single level of prerequisites, we treat $state->originalFlag as the first
        // element in the stack.
        $flagKey = $flag->getKey();
        if ($flag !== $state->originalFlag) {
            if ($state->prerequisiteStack === null) {
                $state->prerequisiteStack = [];
            }
            $state->prerequisiteStack[] = $flagKey;
        }
        try {
            foreach ($flag->getPrerequisites() as $prereq) {
                $prereqKey = $prereq->getKey();

                if ($prereqKey === $state->originalFlag->getKey() ||
                    ($state->prerequisiteStack !== null && in_array($prereqKey, $state->prerequisiteStack))) {
                    throw new EvaluationException(
                        "prerequisite relationship to \"$prereqKey\" caused a circular reference; this is probably a temporary condition due to an incomplete update",
                        EvaluationReason::MALFORMED_FLAG_ERROR
                    );
                }

                if ($state->depth == 0) {
                    if ($state->prerequisites === null) {
                        $state->prerequisites = [];
                    }
                    $state->prerequisites[] = $prereqKey;
                }


                $prereqOk = true;
                $prereqFeatureFlag = $this->_featureRequester->getFeature($prereqKey);
                if ($prereqFeatureFlag === null) {
                    $prereqOk = false;
                } else {
                    // Note that if the prerequisite flag is off, we don't consider it a match no matter what its
                    // off variation was. But we still need to evaluate it in order to generate an event.
                    $state->depth++;
                    $prereqEvalResult = $this->evaluateInternal($prereqFeatureFlag, $context, $prereqEvalSink, $state);
                    $state->depth--;
                    $variation = $prereq->getVariation();
                    if (!$prereqFeatureFlag->isOn() || $prereqEvalResult->getDetail()->getVariationIndex() !== $variation) {
                        $prereqOk = false;
                    }
                    if ($prereqEvalSink !== null) {
                        $prereqEvalSink(new PrerequisiteEvaluationRecord($prereqFeatureFlag, $flag, $prereqEvalResult));
                    }
                }
                if (!$prereqOk) {
                    return EvaluationReason::prerequisiteFailed($prereqKey);
                }
            }
        } finally {
            if ($state->prerequisiteStack !== null && count($state->prerequisiteStack) !== 0) {
                array_pop($state->prerequisiteStack);
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

    private function ruleMatchesContext(Rule $rule, LDContext $context, EvaluatorState $state): bool
    {
        foreach ($rule->getClauses() as $clause) {
            if (!$this->clauseMatchesContext($clause, $context, $state)) {
                return false;
            }
        }
        return true;
    }

    private function clauseMatchesContext(Clause $clause, LDContext $context, EvaluatorState $state): bool
    {
        if ($clause->getOp() === 'segmentMatch') {
            foreach ($clause->getValues() as $segmentKey) {
                if ($state->segmentStack !== null && in_array($segmentKey, $state->segmentStack)) {
                    throw new EvaluationException(
                        "segment rule referencing segment \"$segmentKey\" caused a circular reference; this is probably a temporary condition due to an incomplete update",
                        EvaluationReason::MALFORMED_FLAG_ERROR
                    );
                }
                $segment = $this->_featureRequester->getSegment($segmentKey);
                if ($segment) {
                    if ($this->segmentMatchesContext($segment, $context, $state)) {
                        return EvaluatorHelpers::maybeNegate($clause, true);
                    }
                }
            }
            return EvaluatorHelpers::maybeNegate($clause, false);
        }
        return EvaluatorHelpers::matchClauseWithoutSegments($clause, $context);
    }

    private function segmentMatchesContext(Segment $segment, LDContext $context, EvaluatorState $state): bool
    {
        if ($segment->getUnbounded()) {
            return $this->bigSegmentsContextMatch($segment, $context, $state);
        }

        return $this->simpleSegmentContextMatch($segment, $context, $state, true);
    }

    private function simpleSegmentContextMatch(Segment $segment, LDContext $context, EvaluatorState $state, bool $useIncludes): bool
    {
        if ($useIncludes) {
            if (EvaluatorHelpers::contextKeyIsInTargetList($context, null, $segment->getIncluded())) {
                return true;
            }
            foreach ($segment->getIncludedContexts() as $t) {
                if (EvaluatorHelpers::contextKeyIsInTargetList($context, $t->getContextKind(), $t->getValues())) {
                    return true;
                }
            }
            if (EvaluatorHelpers::contextKeyIsInTargetList($context, null, $segment->getExcluded())) {
                return false;
            }
            foreach ($segment->getExcludedContexts() as $t) {
                if (EvaluatorHelpers::contextKeyIsInTargetList($context, $t->getContextKind(), $t->getValues())) {
                    return false;
                }
            }
        }

        $rules = $segment->getRules();
        if (count($rules) !== 0) {
            // Evaluating rules means we might be doing recursive segment matches, so we'll push the current
            // segment key onto the stack for cycle detection.
            if ($state->segmentStack === null) {
                $state->segmentStack = [];
            }
            $state->segmentStack[] = $segment->getKey();
            try {
                foreach ($rules as $rule) {
                    if ($this->segmentRuleMatchesContext($rule, $context, $segment->getKey(), $segment->getSalt(), $state)) {
                        return true;
                    }
                }
            } finally {
                array_pop($state->segmentStack);
            }
        }
        return false;
    }

    private function bigSegmentsContextMatch(Segment $segment, LDContext $context, EvaluatorState $state): bool
    {
        if ($segment->getGeneration() === null) {
            $state->bigSegmentsEvaluationStatus = BigSegmentsEvaluationStatus::NOT_CONFIGURED;
            return false;
        }

        $matchedContext = $context->getIndividualContext($segment->getUnboundedContextKind());
        if ($matchedContext === null) {
            return false;
        }

        /** @var ?array<string, bool> */
        $membership = null;
        if ($state->bigSegmentsMembership !== null) {
            $membership = $state->bigSegmentsMembership[$matchedContext->getKey()] ?? null;
        }

        if ($membership === null) {
            // Note that this query is just by key; the context kind doesn't
            // matter because any given Big Segment can only reference one
            // context kind. So if segment A for the "user" kind includes a
            // "user" context with key X, and segment B for the "org" kind
            // includes an "org" context with the same key X, it is fine to say
            // that the membership for key X is segment A and segment B-- there
            // is no ambiguity.
            $result = $this->_bigSegmentsStoreManager->getContextMembership($matchedContext->getKey());
            if ($result !== null) {
                $state->bigSegmentsEvaluationStatus = $result->status;

                $membership = $result->membership;
                if ($state->bigSegmentsMembership === null) {
                    $state->bigSegmentsMembership = [];
                }
                $state->bigSegmentsMembership[$matchedContext->getKey()] = $membership;
            } else {
                $state->bigSegmentsEvaluationStatus = BigSegmentsEvaluationStatus::NOT_CONFIGURED;
            }
        }

        $membershipResult = null;
        if ($membership !== null) {
            $segmentRef = Evaluator::makeBigSegmentsRef($segment);
            $membershipResult = $membership[$segmentRef] ?? false;
        }

        if ($membershipResult !== null) {
            return $membershipResult;
        }

        return $this->simpleSegmentContextMatch($segment, $context, $state, false);
    }

    private static function makeBigSegmentsRef(Segment $segment): string
    {
        // The format of Big Segment references is independent of what store
        // implementation is being used; the store implementation receives only
        // this string and does not know the details of the data model. The
        // Relay Proxy will use the same format when writing to the store.
        return sprintf("%s.g%s", $segment->getKey(), $segment->getGeneration() ?? '');
    }

    private function segmentRuleMatchesContext(
        SegmentRule $rule,
        LDContext $context,
        string $segmentKey,
        string $segmentSalt,
        EvaluatorState $state
    ): bool {
        foreach ($rule->getClauses() as $clause) {
            if (!$this->clauseMatchesContext($clause, $context, $state)) {
                return false;
            }
        }
        // If the weight is absent, this rule matches
        if ($rule->getWeight() === null) {
            return true;
        }
        // All of the clauses are met. See if the user buckets in
        $bucketBy = $rule->getBucketBy() ?: 'key';
        try {
            $bucket = EvaluatorBucketing::getBucketValueForContext(
                $context,
                $rule->getRolloutContextKind(),
                $segmentKey,
                $bucketBy,
                $segmentSalt,
                null
            );
        } catch (InvalidAttributeReferenceException $e) {
            return false;
        }
        $weight = $rule->getWeight() / 100000.0;
        return $bucket < $weight;
    }
}
