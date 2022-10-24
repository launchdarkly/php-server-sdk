<?php

declare(strict_types=1);

namespace LaunchDarkly\Impl;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;
use LaunchDarkly\Subsystems\FeatureRequester;

/**
 * Internal class used in LDClient.
 *
 * @ignore
 * @internal
 */
class PreloadedFeatureRequester implements FeatureRequester
{
    private FeatureRequester $_baseRequester;
    private array $_knownFeatures;

    public function __construct(FeatureRequester $baseRequester, array $knownFeatures)
    {
        $this->_baseRequester = $baseRequester;
        $this->_knownFeatures = $knownFeatures;
    }

    /**
     * Gets feature data from cached values
     *
     * @param string $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag
    {
        return $this->_knownFeatures[$key] ?? null;
    }

    /**
     * Gets segment data from the regular feature requester
     *
     * @param string $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment
    {
        return $this->_baseRequester->getSegment($key);
    }

    /**
     * Gets all features from cached values
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array
    {
        return $this->_knownFeatures;
    }
}
