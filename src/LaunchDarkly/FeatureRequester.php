<?php
namespace LaunchDarkly;

use LaunchDarkly\Impl\Model\FeatureFlag;
use LaunchDarkly\Impl\Model\Segment;

/**
 * Interface for the component that retrieves feature flag data.
 *
 * The default implementation is {@see \LaunchDarkly\Integrations\Guzzle::featureRequester()}, which requests
 * flags inefficiently via HTTP on demand. For other implementations, including database integrations, see the
 * LaunchDarkly\Integrations namespace.
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
     * @return array|null The decoded FeatureFlags, or null if missing
     */
    public function getAllFeatures(): ?array;
}
