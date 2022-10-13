<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDUser;

/**
 * @ignore
 * @internal
 */
class EventFactory
{
    private bool $_withReasons;

    public function __construct(bool $withReasons)
    {
        $this->_withReasons = $withReasons;
    }

    /**
     * @param FeatureFlag $flag
     * @param LDUser $user
     * @param EvaluationDetail $detail
     * @param mixed $default
     * @param FeatureFlag|null $prereqOfFlag
     * @return mixed[]
     */
    public function newEvalEvent(
        FeatureFlag $flag,
        LDUser $user,
        EvaluationDetail $detail,
        mixed $default,
        ?FeatureFlag $prereqOfFlag = null
    ): array {
        $addExperimentData = $flag->isExperiment($detail->getReason());
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
        if (($addExperimentData || $this->_withReasons)) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return mixed[]
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
        if ($this->_withReasons) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return mixed[]
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
        if ($this->_withReasons) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        if ($user->getAnonymous()) {
            $e['contextKind'] = 'anonymousUser';
        }
        return $e;
    }

    /**
     * @return mixed[]
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
     * @param mixed $data
     * @param int|float|null $metricValue
     *
     * @return mixed[]
     */
    public function newCustomEvent(string $eventName, LDUser $user, mixed $data, int|float|null $metricValue): array
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

    private static function contextKind(LDUser $user): string
    {
        if ($user->getAnonymous()) {
            return 'anonymousUser';
        } else {
            return 'user';
        }
    }
}
