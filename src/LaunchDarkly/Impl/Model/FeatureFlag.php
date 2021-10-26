<?php

namespace LaunchDarkly\Impl\Model;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\FeatureRequester;
use LaunchDarkly\Impl\EvalResult;
use LaunchDarkly\Impl\Events\EventFactory;
use LaunchDarkly\LDUser;

/**
 * Internal data model class that describes a feature flag configuration.
 *
 * Application code should never need to reference the data model directly.
 *
 * @ignore
 * @internal
 */
class FeatureFlag
{
    /** @var int */
    protected static $LONG_SCALE = 0xFFFFFFFFFFFFFFF;

    /** @var string */
    protected $_key;
    /** @var int */
    protected $_version;
    /** @var bool */
    protected $_on = false;
    /** @var Prerequisite[] */
    protected $_prerequisites = [];
    /** @var string|null */
    protected $_salt = null;
    /** @var Target[] */
    protected $_targets = [];
    /** @var Rule[] */
    protected $_rules = [];
    /** @var VariationOrRollout */
    protected $_fallthrough;
    /** @var int | null */
    protected $_offVariation = null;
    /** @var array */
    protected $_variations = [];
    /** @var bool */
    protected $_deleted = false;
    /** @var bool */
    protected $_trackEvents = false;
    /** @var bool */
    protected $_trackEventsFallthrough = false;
    /** @var int | null */
    protected $_debugEventsUntilDate = null;
    /** @var bool */
    protected $_clientSide = false;

    // Note, trackEvents and debugEventsUntilDate are not used in EventProcessor, because
    // the PHP client doesn't do summary events. However, we need to capture them in case
    // they want to pass the flag data to the front end with allFlagsState().

    protected function __construct(
        string $key,
        int $version,
        bool $on,
        array $prerequisites,
        ?string $salt,
        array $targets,
        array $rules,
        VariationOrRollout $fallthrough,
        ?int $offVariation,
        array $variations,
        bool $deleted,
        bool $trackEvents,
        bool $trackEventsFallthrough,
        ?int $debugEventsUntilDate,
        bool $clientSide
    ) {
        $this->_key = $key;
        $this->_version = $version;
        $this->_on = $on;
        $this->_prerequisites = $prerequisites;
        $this->_salt = $salt;
        $this->_targets = $targets;
        $this->_rules = $rules;
        $this->_fallthrough = $fallthrough;
        $this->_offVariation = $offVariation;
        $this->_variations = $variations;
        $this->_deleted = $deleted;
        $this->_trackEvents = $trackEvents;
        $this->_trackEventsFallthrough = $trackEventsFallthrough;
        $this->_debugEventsUntilDate = $debugEventsUntilDate;
        $this->_clientSide = $clientSide;
    }

    /**
     * @return \Closure
     *
     * @psalm-return \Closure(mixed):self
     */
    public static function getDecoder(): \Closure
    {
        return function ($v) {
            return new FeatureFlag(
                $v['key'],
                $v['version'],
                $v['on'],
                array_map(Prerequisite::getDecoder(), $v['prerequisites'] ?: []),
                $v['salt'],
                array_map(Target::getDecoder(), $v['targets'] ?: []),
                array_map(Rule::getDecoder(), $v['rules'] ?: []),
                call_user_func(VariationOrRollout::getDecoder(), $v['fallthrough']),
                $v['offVariation'],
                $v['variations'] ?: [],
                $v['deleted'],
                isset($v['trackEvents']) && $v['trackEvents'],
                isset($v['trackEventsFallthrough']) && $v['trackEventsFallthrough'],
                isset($v['debugEventsUntilDate']) ? $v['debugEventsUntilDate'] : null,
                isset($v['clientSide']) && $v['clientSide']
            );
        };
    }

    public static function decode(array $v): self
    {
        $decoder = FeatureFlag::getDecoder();
        return $decoder($v);
    }

    public function isOn(): bool
    {
        return $this->_on;
    }

    public function evaluate(LDUser $user, FeatureRequester $featureRequester, EventFactory $eventFactory): EvalResult
    {
        $prereqEvents = [];
        $detail = $this->evaluateInternal($user, $featureRequester, $prereqEvents, $eventFactory);
        return new EvalResult($detail, $prereqEvents);
    }

    private function evaluateInternal(
        LDUser $user,
        FeatureRequester $featureRequester,
        array &$events,
        EventFactory $eventFactory
    ): EvaluationDetail {
        if (!$this->isOn()) {
            return $this->getOffValue(EvaluationReason::off());
        }

        $prereqFailureReason = $this->checkPrerequisites($user, $featureRequester, $events, $eventFactory);
        if ($prereqFailureReason !== null) {
            return $this->getOffValue($prereqFailureReason);
        }

        // Check to see if targets match
        if ($this->_targets != null) {
            foreach ($this->_targets as $target) {
                foreach ($target->getValues() as $value) {
                    if ($value === $user->getKey()) {
                        return $this->getVariation($target->getVariation(), EvaluationReason::targetMatch());
                    }
                }
            }
        }
        // Now walk through the rules and see if any match
        if ($this->_rules != null) {
            foreach ($this->_rules as $i => $rule) {
                if ($rule->matchesUser($user, $featureRequester)) {
                    return $this->getValueForVariationOrRollout(
                        $rule,
                        $user,
                        EvaluationReason::ruleMatch($i, $rule->getId())
                    );
                }
            }
        }
        return $this->getValueForVariationOrRollout($this->_fallthrough, $user, EvaluationReason::fallthrough());
    }

    private function checkPrerequisites(LDUser $user, FeatureRequester $featureRequester, array &$events, EventFactory $eventFactory): ?EvaluationReason
    {
        if ($this->_prerequisites != null) {
            foreach ($this->_prerequisites as $prereq) {
                $prereqOk = true;
                try {
                    $prereqEvalResult = null;
                    $prereqFeatureFlag = $featureRequester->getFeature($prereq->getKey());
                    if ($prereqFeatureFlag == null) {
                        $prereqOk = false;
                    } else {
                        $prereqEvalResult = $prereqFeatureFlag->evaluateInternal($user, $featureRequester, $events, $eventFactory);
                        $variation = $prereq->getVariation();
                        if (!$prereqFeatureFlag->isOn() || $prereqEvalResult->getVariationIndex() !== $variation) {
                            $prereqOk = false;
                        }
                        $events[] = $eventFactory->newEvalEvent($prereqFeatureFlag, $user, $prereqEvalResult, null, $this);
                    }
                } catch (\Exception $e) {
                    $prereqOk = false;
                }
                if (!$prereqOk) {
                    return EvaluationReason::prerequisiteFailed($prereq->getKey());
                }
            }
        }
        return null;
    }

    private function getVariation(int $index, EvaluationReason $reason): EvaluationDetail
    {
        if ($index < 0 || $index >= count($this->_variations)) {
            return new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        }
        return new EvaluationDetail($this->_variations[$index], $index, $reason);
    }

    private function getOffValue(EvaluationReason $reason): EvaluationDetail
    {
        if ($this->_offVariation === null) {
            return new EvaluationDetail(null, null, $reason);
        }
        return $this->getVariation($this->_offVariation, $reason);
    }
    
    private function getValueForVariationOrRollout(VariationOrRollout $r, LDUser $user, EvaluationReason $reason): EvaluationDetail
    {
        $rollout = $r->getRollout();
        list($index, $inExperiment) = $r->variationIndexForUser($user, $this->_key, $this->_salt);
        if ($index === null) {
            return new EvaluationDetail(null, null, EvaluationReason::error(EvaluationReason::MALFORMED_FLAG_ERROR));
        }
        if ($inExperiment) {
            if ($reason->getKind() === EvaluationReason::FALLTHROUGH) {
                $reason = EvaluationReason::fallthrough(true);
            } elseif ($reason->getKind() === EvaluationReason::RULE_MATCH) {
                $reason = EvaluationReason::ruleMatch($reason->getRuleIndex(), $reason->getRuleId(), true);
            }
        }
        return $this->getVariation($index, $reason);
    }

    public function getVersion(): int
    {
        return $this->_version;
    }

    public function getKey(): string
    {
        return $this->_key;
    }

    public function isDeleted(): bool
    {
        return $this->_deleted;
    }

    public function getRules(): array
    {
        return $this->_rules;
    }
    
    public function isTrackEvents(): bool
    {
        return $this->_trackEvents;
    }

    public function isTrackEventsFallthrough(): bool
    {
        return $this->_trackEventsFallthrough;
    }

    public function getDebugEventsUntilDate(): ?int
    {
        return $this->_debugEventsUntilDate;
    }

    public function isClientSide(): bool
    {
        return $this->_clientSide;
    }
}
