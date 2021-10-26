<?php

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\EvaluationReason;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDUser;

/**
 * @ignore
 * @internal
 */
class EventFactory
{
    /** @var boolean */
    private $_withReasons;

    public function __construct(bool $withReasons)
    {
        $this->_withReasons = $withReasons;
    }

    /**
     * @param FeatureFlag $flag
     * @param LDUser $user
     * @param EvaluationDetail $detail
     * @param mixed|null $default
     * @param FeatureFlag|null $prereqOfFlag
     * @return (mixed|null)[]
     */
    public function newEvalEvent(
        FeatureFlag $flag,
        LDUser $user,
        EvaluationDetail $detail,
        $default,
        $prereqOfFlag = null
    ): array {
        $addExperimentData = static::isExperiment($flag, $detail->getReason());
        $e = [
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $flag->getKey(),
            'user' => $user,
            'variation' => $detail->getVariationIndex(),
            'value' => $detail->getValue(),
            'default' => $default,
            'version' => $flag->getVersion()
        ];
        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($addExperimentData || $flag->isTrackEvents()) {
            $e['trackEvents'] = true;
        }
        if ($flag->getDebugEventsUntilDate()) {
            $e['debugEventsUntilDate'] = $flag->getDebugEventsUntilDate();
        }
        if ($prereqOfFlag) {
            $e['prereqOf'] = $prereqOfFlag->getKey();
        }
        if (($addExperimentData || $this->_withReasons) && $detail->getReason()) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return (mixed|null)[]
     */
    public function newDefaultEvent(FeatureFlag $flag, LDUser $user, EvaluationDetail $detail): array
    {
        $e = [
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $flag->getKey(),
            'user' => $user,
            'value' => $detail->getValue(),
            'default' => $detail->getValue(),
            'version' => $flag->getVersion()
        ];
        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($flag->isTrackEvents()) {
            $e['trackEvents'] = true;
        }
        if ($flag->getDebugEventsUntilDate()) {
            $e['debugEventsUntilDate'] = $flag->getDebugEventsUntilDate();
        }
        if ($this->_withReasons && $detail->getReason()) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return (mixed|null)[]
     */
    public function newUnknownFlagEvent(string $key, LDUser $user, EvaluationDetail $detail): array
    {
        $e = [
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $key,
            'user' => $user,
            'value' => $detail->getValue(),
            'default' => $detail->getValue()
        ];
        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($this->_withReasons && $detail->getReason()) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return (mixed|null)[]
     */
    public function newIdentifyEvent(LDUser $user): array
    {
        return [
            'kind' => 'identify',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => strval($user->getKey()),
            'user' => $user
        ];
    }
    
    /**
     * @param string $eventName
     * @param LDUser $user
     * @param mixed|null $data
     * @param null|numeric $metricValue
     *
     * @return (mixed|null)[]
     */
    public function newCustomEvent(string $eventName, LDUser $user, $data, $metricValue): array
    {
        $e = [
            'kind' => 'custom',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $eventName,
            'user' => $user
        ];
        if (isset($data)) {
            $e['data'] = $data;
        }
        if (isset($metricValue)) {
            $e['metricValue'] = $metricValue;
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return (mixed|null)[]
     */
    public function newAliasEvent(LDUser $user, LDUser $previousUser): array
    {
        $e = [
            'kind' => 'alias',
            'key' => strval($user->getKey()),
            'contextKind' => static::contextKind($user),
            'previousKey' => strval($previousUser->getKey()),
            'previousContextKind' => static::contextKind($previousUser),
            'creationDate' => Util::currentTimeUnixMillis()
        ];

        return $e;
    }

    private static function contextKind(LDUser $user): string
    {
        if ($user->getAnonymous()) {
            return 'anonymousUser';
        } else {
            return 'user';
        }
    }

    private static function isExperiment(FeatureFlag $flag, EvaluationReason $reason): bool
    {
        if ($reason->isInExperiment()) {
            return true;
        }
        switch ($reason->getKind()) {
            case 'RULE_MATCH':
                $i = $reason->getRuleIndex();
                $rules = $flag->getRules();
                return isset($i) && $i >= 0 && $i < count($rules) && $rules[$i]->isTrackEvents();
            case 'FALLTHROUGH':
                return $flag->isTrackEventsFallthrough();
            default:
                return false;
        }
    }
}
