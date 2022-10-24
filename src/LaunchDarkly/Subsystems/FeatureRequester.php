<?php

declare(strict_types=1);

namespace LaunchDarkly\Subsystems;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;

/**
 * Interface for the component that retrieves feature flag data.
 *
 * Application code should not need to implement this interface. LaunchDarkly provides several implementations:
 *
 * - The default, {@see \LaunchDarkly\Integrations\Guzzle::featureRequester()}, which requests
 * flags inefficiently via HTTP on demand.
 * - Database integrations provided in separate packages. See: https://docs.launchdarkly.com/sdk/features/storing-data#php
 * - A mechanism for reading data from the filesystem: {@see \LaunchDarkly\Integrations\Files}
 * - A mechanism for injecting test data: {@see \LaunchDarkly\Integrations\TestData}
 */
interface FeatureRequester
{
    /**
     * Gets the configuration for a specific feature flag.
     *
     * @param string $key feature key
     * @return FeatureFlag|null The decoded FeatureFlag, or null if missing
     */
    public function getFeature(string $key): ?FeatureFlag;

    /**
     * Gets the configuration for a specific user segment.
     *
     * @param string $key segment key
     * @return Segment|null The decoded Segment, or null if missing
     */
    public function getSegment(string $key): ?Segment;

    /**
     * Gets all feature flags.
     *
     * @return array<string, FeatureFlag>|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array;
}
