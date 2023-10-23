<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl\Events;

use LaunchDarkly\EvaluationDetail;
use LaunchDarkly\Impl\Evaluation\EvalResult;
use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Util;
use LaunchDarkly\LDContext;

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
     * @param LDContext $context
     * @param EvalResult $result
     * @param mixed $default
     * @param FeatureFlag|null $prereqOfFlag
     * @return mixed[]
     */
    public function newEvalEvent(
        FeatureFlag $flag,
        LDContext $context,
        EvalResult $result,
        mixed $default,
        ?FeatureFlag $prereqOfFlag = null
    ): array {
        $detail = $result->getDetail();
        $forceReasonTracking = $result->isForceReasonTracking();
        $e = [
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $flag->getKey(),
            'context' => $context,
            'variation' => $detail->getVariationIndex(),
            'value' => $detail->getValue(),
            'default' => $default,
            'version' => $flag->getVersion()
        ];

        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($flag->getExcludeFromSummaries()) {
            $e['excludeFromSummaries'] = true;
        }
        if ($flag->getSamplingRatio() !== 1) {
            $e['samplingRatio'] = $flag->getSamplingRatio();
        }
        if ($forceReasonTracking || $flag->isTrackEvents()) {
            $e['trackEvents'] = true;
        }
        if ($flag->getDebugEventsUntilDate()) {
            $e['debugEventsUntilDate'] = $flag->getDebugEventsUntilDate();
        }
        if ($prereqOfFlag) {
            $e['prereqOf'] = $prereqOfFlag->getKey();
        }
        if (($forceReasonTracking || $this->_withReasons)) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        return $e;
    }

    /**
     * @return mixed[]
     */
    public function newUnknownFlagEvent(string $key, LDContext $context, EvaluationDetail $detail): array
    {
        $e = [
            'kind' => 'feature',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $key,
            'context' => $context,
            'value' => $detail->getValue(),
            'default' => $detail->getValue()
        ];
        // the following properties are handled separately so we don't waste bandwidth on unused keys
        if ($this->_withReasons) {
            $e['reason'] = $detail->getReason()->jsonSerialize();
        }
        return $e;
    }

    /**
     * @return mixed[]
     */
    public function newIdentifyEvent(LDContext $context): array
    {
        return [
            'kind' => 'identify',
            'creationDate' => Util::currentTimeUnixMillis(),
            'context' => $context
        ];
    }

    /**
     * @return mixed[]
     */
    public function newCustomEvent(string $eventName, LDContext $context, mixed $data, int|float|null $metricValue): array
    {
        $e = [
            'kind' => 'custom',
            'creationDate' => Util::currentTimeUnixMillis(),
            'key' => $eventName,
            'context' => $context
        ];
        if ($data !== null) {
            $e['data'] = $data;
        }
        if ($metricValue !== null) {
            $e['metricValue'] = $metricValue;
        }
        return $e;
    }
}
