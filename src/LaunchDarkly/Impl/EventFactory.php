<?php
namespace LaunchDarkly\Impl;

use LaunchDarkly\Util;

class EventFactory
{
    /** @var boolean */
    private $_withReasons;

    public function __construct($withReasons)
    {
        $this->_withReasons = $withReasons;
    }

    public function newEvalEvent($flag, $user, $detail, $default, $prereqOfFlag = null)
    {
        $addExperimentData = static::isExperiment($flag, $detail->getReason());
        $e = array(
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $flag->getKey(),
            'user' => $user,
            'variation' => $detail->getVariationIndex(),
            'value' => $detail->getValue(),
            'default' => $default,
            'version' => $flag->getVersion()
        );
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
        return $e;
    }

    public function newDefaultEvent($flag, $user, $detail)
    {
        $e = array(
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $flag->getKey(),
            'user' => $user,
            'value' => $detail->getValue(),
            'default' => $detail->getValue(),
            'version' => $flag->getVersion()
        );
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
        return $e;
    }

    public function newUnknownFlagEvent($key, $user, $detail)
    {
        $e = array(
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $key,
            'user' => $user,
            'value' => $detail->getValue(),
            'default' => $detail->getValue()
        );
        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($this->_withReasons && $detail->getReason()) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        return $e;
    }

    public function newIdentifyEvent($user)
    {
        return array(
            'kind' => 'identify',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => strval($user->getKey()),
            'user' => $user
        );
    }
    
    public function newCustomEvent($eventName, $user, $data, $metricValue)
    {
        $e = array(
            'kind' => 'custom',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $eventName,
            'user' => $user
        );
        if (isset($data)) {
            $e['data'] = $data;
        }
        if (isset($metricValue)) {
            $e['metricValue'] = $metricValue;
        }
        return $e;
    }

    private static function isExperiment($flag, $reason)
    {
        if ($reason) {
            switch ($reason->getKind()) {
                case 'RULE_MATCH':
                    $i = $reason->getRuleIndex();
                    $rules = $flag->getRules();
                    return isset($i) && $i >= 0 && $i < count($rules) && $rules[$i]->isTrackEvents();
                case 'FALLTHROUGH':
                    return $flag->isTrackEventsFallthrough();
            }
        }
        return false;
    }
}
